<?php

namespace Tests\Feature\Admin;

use App\Exceptions\CourierFailed;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\CourierIndex;
use App\Livewire\Admin\OrderShow;
use App\Models\Courier;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Orders\OrderFlow;
use App\Services\Orders\PlaceOrder;
use App\Services\Storefront\Basket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Talking to the couriers themselves.
 *
 * Nothing here touches a real courier. Every answer is a stand-in, so these
 * prove our side of the conversation: that we send what each courier asks
 * for, that a courier refusing a parcel leaves the order exactly where it
 * was, and — most of all — that a courier the shopkeeper has not switched to
 * automatic is never contacted at all.
 */
class CourierApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod', 'is_enabled' => true, 'position' => 0,
        ]);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id, 'name' => 'Jewel Rana']));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function flow(): OrderFlow
    {
        return app(OrderFlow::class);
    }

    /**
     * An order for 260 Taka, cash on delivery, packed and ready to go.
     */
    protected function readyToSend(): Order
    {
        $created = app(ProductService::class)->create([
            'name' => 'Basmati rice '.uniqid(), 'regular_price' => '100', 'stock' => 50,
            'status' => Product::STATUS_ACTIVE, 'unit' => 'per kg',
        ]);

        $product = $created instanceof Product ? $created : $created->product;

        $area = DeliveryArea::firstOrCreate(
            ['tenant_id' => $this->store->id, 'name' => 'Mirpur'],
            ['latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 10, 'delivery_charge_minor' => 6000],
        );

        request()->setLaravelSession(app('session.store'));

        $basket = app(Basket::class);
        $basket->add($product->fresh()->variants->first(), 2);

        $order = app(PlaceOrder::class)->place(
            $basket,
            ['name' => 'A Shopper', 'phone' => '+8801711223344', 'address' => 'House 4, Road 5, Mirpur, Dhaka'],
            'cod',
            $area,
        );

        return $this->flow()->apply(
            $this->flow()->apply($order, Order::STATUS_APPROVED),
            Order::STATUS_PROCESSING,
        );
    }

    protected function pathao(bool $complete = true): Courier
    {
        $courier = new Courier([
            'tenant_id' => $this->store->id,
            'name' => 'Pathao Courier',
            'driver' => 'pathao',
            'mode' => Courier::AUTOMATIC,
            'settings' => ['client_id' => 'client-1234', 'username' => 'shop@example.com', 'store_id' => '77', 'sandbox' => true],
        ]);

        $courier->credentials = $complete
            ? ['client_secret' => 'super-secret-client-secret', 'password' => 'super-secret-password']
            : [];

        $courier->save();

        return $courier;
    }

    protected function steadfast(): Courier
    {
        $courier = new Courier([
            'tenant_id' => $this->store->id,
            'name' => 'Steadfast Courier',
            'driver' => 'steadfast',
            'mode' => Courier::AUTOMATIC,
        ]);

        $courier->credentials = ['api_key' => 'the-api-key', 'secret_key' => 'the-secret-key'];
        $courier->save();

        return $courier;
    }

    protected function redx(): Courier
    {
        $courier = new Courier([
            'tenant_id' => $this->store->id,
            'name' => 'RedX',
            'driver' => 'redx',
            'mode' => Courier::AUTOMATIC,
            'settings' => ['pickup_store_id' => '901'],
        ]);

        $courier->credentials = ['access_token' => 'the-redx-jwt-token'];
        $courier->save();

        return $courier;
    }

    /**
     * @return array{text: string, tone: string|null}
     */
    protected function toast(array $params): array
    {
        return ['text' => '', 'tone' => null, ...($params[0] ?? [])];
    }

    protected function pathaoFakes(array $extra = []): array
    {
        return [
            '*/issue-token' => Http::response(['access_token' => 'a-stand-in-token', 'expires_in' => 432000]),
            '*/city-list' => Http::response(['data' => ['data' => [['city_id' => 1, 'city_name' => 'Dhaka']]]]),
            '*/zone-list' => Http::response(['data' => ['data' => [['zone_id' => 9, 'zone_name' => 'Mirpur']]]]),
            '*/area-list' => Http::response(['data' => ['data' => [['area_id' => 33, 'area_name' => 'Mirpur 10']]]]),
            ...$extra,
        ];
    }

    /*
     * ---------------------------------------------------------------
     * A courier nobody switched on is never contacted
     * ---------------------------------------------------------------
     */

    public function test_a_courier_left_by_hand_is_never_contacted(): void
    {
        Http::fake();

        // The module exists, but the shopkeeper has not switched it on.
        $courier = $this->pathao();
        $courier->update(['mode' => Courier::MANUAL]);

        $order = $this->flow()->apply($this->readyToSend(), Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'tracking_code' => 'TYPED-BY-HAND',
        ]);

        $this->assertSame('TYPED-BY-HAND', $order->tracking_code);
        Http::assertNothingSent();
    }

    public function test_a_courier_with_details_missing_books_nothing(): void
    {
        Http::fake();

        $courier = $this->pathao(complete: false);

        $order = $this->flow()->apply($this->readyToSend(), Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'tracking_code' => 'TYPED-BY-HAND',
        ]);

        $this->assertSame('TYPED-BY-HAND', $order->tracking_code);
        Http::assertNothingSent();
    }

    /*
     * ---------------------------------------------------------------
     * Pathao
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_check_their_pathao_details(): void
    {
        Http::fake($this->pathaoFakes());

        $courier = $this->pathao();

        Livewire::test(CourierIndex::class)
            ->call('test', $courier->id)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'ok'
                && str_contains($this->toast($params)['text'], 'test system'));

        // It read a list. It booked nothing.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/orders'));
    }

    public function test_pathao_refusing_the_details_is_explained_plainly(): void
    {
        Http::fake(['*/issue-token' => Http::response(['message' => 'Unauthenticated'], 401)]);

        $courier = $this->pathao();

        Livewire::test(CourierIndex::class)
            ->call('test', $courier->id)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'bad'
                && str_contains($this->toast($params)['text'], 'did not accept this shop'));
    }

    public function test_handing_over_books_the_parcel_with_pathao(): void
    {
        Http::fake($this->pathaoFakes([
            '*/aladdin/api/v1/orders' => Http::response([
                'message' => 'Order Created Successfully',
                'data' => [
                    'consignment_id' => 'DH250908ABCDE',
                    'merchant_order_id' => 'ORDER-1',
                    'order_status' => 'Pending',
                    'delivery_fee' => 60,
                ],
            ]),
        ]));

        $courier = $this->pathao();
        $order = $this->readyToSend();

        $moved = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'city' => '1', 'zone' => '9', 'area' => '33',
        ]);

        $this->assertSame(Order::STATUS_HANDED_OVER, $moved->status);
        $this->assertSame('DH250908ABCDE', $moved->tracking_code);
        $this->assertSame('Pathao Courier', $moved->courier_name);

        Http::assertSent(function (Request $r) use ($order) {
            if (! str_ends_with($r->url(), '/aladdin/api/v1/orders')) {
                return false;
            }

            $sent = $r->data();

            return $sent['store_id'] === 77
                && $sent['merchant_order_id'] === $order->reference
                && $sent['recipient_name'] === 'A Shopper'
                // +8801711223344 is the same number as 01711223344.
                && $sent['recipient_phone'] === '01711223344'
                && $sent['recipient_city'] === 1
                && $sent['recipient_zone'] === 9
                && $sent['recipient_area'] === 33
                // Cash on delivery: 260 Taka to collect at the door.
                && $sent['amount_to_collect'] === 260;
        });
    }

    public function test_pathao_refusing_the_parcel_leaves_the_order_where_it_was(): void
    {
        Http::fake($this->pathaoFakes([
            '*/aladdin/api/v1/orders' => Http::response([
                'message' => 'Please fix the given errors',
                'errors' => ['recipient_phone' => ['The recipient phone must be 11 digits.']],
            ], 422),
        ]));

        $courier = $this->pathao();
        $order = $this->readyToSend();

        try {
            $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
                'courier_id' => $courier->id, 'city' => '1', 'zone' => '9', 'area' => '33',
            ]);
            $this->fail('The parcel should not have been accepted.');
        } catch (CourierFailed $e) {
            $this->assertStringContainsString('11 digits', $e->getMessage());
        }

        $order->refresh();

        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertNull($order->tracking_code);
        $this->assertNull($order->courier_id);
    }

    public function test_the_order_screen_asks_pathao_for_its_own_city_zone_and_area(): void
    {
        Http::fake($this->pathaoFakes());

        $this->pathao();
        $order = $this->readyToSend();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('choose', Order::STATUS_HANDED_OVER)
            ->assertSee('City')
            // Nothing below the city until a city is chosen.
            ->assertSee('Choose the city first')
            ->set('booking.city', '1')
            ->assertSee('Mirpur')
            ->set('booking.zone', '9')
            ->assertSee('Mirpur 10');
    }

    public function test_a_parcel_sent_out_again_is_booked_under_its_own_number(): void
    {
        Http::fake($this->pathaoFakes([
            '*/aladdin/api/v1/orders' => Http::response(['data' => ['consignment_id' => 'DH-SECOND-TRY']]),
        ]));

        $courier = $this->pathao();
        $order = $this->readyToSend();

        $order = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'city' => '1', 'zone' => '9', 'area' => '33',
        ]);
        $order = $this->flow()->apply($order, Order::STATUS_NOT_DELIVERED, ['reason' => 'Nobody at home']);
        $order = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'city' => '1', 'zone' => '9', 'area' => '33',
        ]);

        $reference = $order->reference;

        // Couriers keep these unique, so the second parcel needs its own.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/aladdin/api/v1/orders')
            && ($r->data()['merchant_order_id'] ?? '') === $reference.'-2');
    }

    public function test_an_order_already_paid_for_online_has_nothing_to_collect(): void
    {
        Http::fake($this->pathaoFakes([
            '*/aladdin/api/v1/orders' => Http::response(['data' => ['consignment_id' => 'DH-PAID']]),
        ]));

        $courier = $this->pathao();
        $order = $this->readyToSend();
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID])->save();

        $this->flow()->apply($order->fresh(), Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'city' => '1', 'zone' => '9', 'area' => '33',
        ]);

        Http::assertSent(fn (Request $r) => ! str_ends_with($r->url(), '/aladdin/api/v1/orders')
            || ($r->data()['amount_to_collect'] ?? null) === 0);
    }

    /*
     * ---------------------------------------------------------------
     * Steadfast
     * ---------------------------------------------------------------
     */

    public function test_handing_over_books_the_parcel_with_steadfast(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => 200,
                'message' => 'Consignment has been created successfully.',
                'consignment' => [
                    'consignment_id' => 1424107,
                    'invoice' => 'ORDER-1',
                    'tracking_code' => '15D7APVP',
                    'status' => 'in_review',
                ],
            ]),
        ]);

        $courier = $this->steadfast();
        $order = $this->readyToSend();

        $moved = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, ['courier_id' => $courier->id]);

        // The tracking code is what a customer follows.
        $this->assertSame('15D7APVP', $moved->tracking_code);

        Http::assertSent(function (Request $r) use ($order) {
            $sent = $r->data();

            return str_ends_with($r->url(), '/create_order')
                && $r->hasHeader('Api-Key', 'the-api-key')
                && $r->hasHeader('Secret-Key', 'the-secret-key')
                && $sent['invoice'] === $order->reference
                && $sent['recipient_phone'] === '01711223344'
                && $sent['cod_amount'] === 260;
        });
    }

    public function test_steadfast_saying_it_already_has_that_parcel_is_explained_plainly(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => 400, 'message' => 'The invoice has already been taken.',
            ], 400),
        ]);

        $courier = $this->steadfast();

        $this->expectExceptionMessage('already has a parcel with this order');

        $this->flow()->apply($this->readyToSend(), Order::STATUS_HANDED_OVER, ['courier_id' => $courier->id]);
    }

    public function test_the_shopkeeper_can_ask_steadfast_where_the_parcel_is(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => 200,
                'consignment' => ['consignment_id' => 1424107, 'tracking_code' => '15D7APVP'],
            ]),
            '*/status_by_trackingcode/*' => Http::response(['status' => 200, 'delivery_status' => 'delivered']),
        ]);

        $courier = $this->steadfast();
        $order = $this->flow()->apply($this->readyToSend(), Order::STATUS_HANDED_OVER, ['courier_id' => $courier->id]);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('askCourier')
            ->assertSet('courierSays', 'Delivered')
            // What they say does not move the order.
            ->assertSee('still your decision');

        $this->assertSame(Order::STATUS_HANDED_OVER, $order->fresh()->status);
    }

    /*
     * ---------------------------------------------------------------
     * RedX
     * ---------------------------------------------------------------
     */

    public function test_handing_over_books_the_parcel_with_redx(): void
    {
        Http::fake([
            '*/areas' => Http::response(['areas' => [['id' => 5, 'name' => 'Mirpur 10', 'district_name' => 'Dhaka']]]),
            '*/parcel' => Http::response(['tracking_id' => '25AB7X9Y']),
        ]);

        $courier = $this->redx();
        $order = $this->readyToSend();

        $moved = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'area' => '5',
        ]);

        $this->assertSame('25AB7X9Y', $moved->tracking_code);

        Http::assertSent(function (Request $r) use ($order) {
            if (! str_ends_with($r->url(), '/parcel')) {
                return false;
            }

            $sent = $r->data();

            return $r->hasHeader('API-ACCESS-TOKEN', 'Bearer the-redx-jwt-token')
                && $sent['delivery_area_id'] === 5
                && $sent['merchant_invoice_id'] === $order->reference
                && $sent['cash_collection_amount'] === '260'
                && $sent['pickup_store_id'] === 901;
        });
    }

    public function test_redx_needs_an_area_chosen(): void
    {
        Http::fake(['*/areas' => Http::response(['areas' => []])]);

        $courier = $this->redx();

        $this->expectExceptionMessage('Choose the delivery area');

        $this->flow()->apply($this->readyToSend(), Order::STATUS_HANDED_OVER, ['courier_id' => $courier->id]);
    }

    /*
     * ---------------------------------------------------------------
     * Keeping a shop's details to itself
     * ---------------------------------------------------------------
     */

    public function test_details_are_stored_encrypted_and_shown_back_only_as_their_last_four(): void
    {
        Livewire::test(CourierIndex::class)
            ->call('add')
            ->set('name', 'Steadfast Courier')
            ->set('driver', 'steadfast')
            ->set('mode', Courier::AUTOMATIC)
            ->set('form.api_key', 'abcdefgh12345678')
            ->set('form.secret_key', 'zyxwvuts87654321')
            ->call('save')
            ->assertHasNoErrors();

        $courier = Courier::where('name', 'Steadfast Courier')->firstOrFail();

        $this->assertSame('abcdefgh12345678', $courier->credentials['api_key']);
        $this->assertSame('••••••••5678', $courier->maskedSecret('api_key'));

        // Never in the model's own array form, and never in the database as
        // anything a person could read.
        $this->assertArrayNotHasKey('credentials', $courier->toArray());
        $this->assertStringNotContainsString(
            'abcdefgh12345678',
            (string) DB::table('couriers')->where('id', $courier->id)->value('credentials'),
        );
    }

    public function test_a_courier_token_never_reaches_a_message_a_person_could_read(): void
    {
        Http::fake(['*/areas' => Http::response(['message' => 'bad token the-redx-jwt-token'], 401)]);

        $courier = $this->redx();

        Livewire::test(CourierIndex::class)
            ->call('test', $courier->id)
            ->assertDispatched('toast', fn ($event, $params) => ! str_contains(
                $this->toast($params)['text'],
                'the-redx-jwt-token',
            ));
    }

    public function test_one_shop_never_sees_another_shops_courier_account(): void
    {
        $mine = $this->steadfast();

        $other = Tenant::factory()->create(['currency' => 'BDT']);

        Tenancy::run($other, function () use ($mine) {
            $this->assertNull(Courier::find($mine->id));
            $this->assertSame(0, Courier::count());
        });
    }

    public function test_the_couriers_screen_offers_the_modules_this_shop_can_talk_to(): void
    {
        Livewire::test(CourierIndex::class)
            ->call('add')
            ->assertSee('I book parcels with them myself')
            ->assertSee('Pathao Courier')
            ->assertSee('Steadfast Courier')
            ->assertSee('RedX')
            ->set('driver', 'pathao')
            // A module chosen is not a module switched on: the details are
            // only asked for once the shopkeeper says they want it automatic.
            ->assertDontSee('Client ID')
            ->set('mode', Courier::AUTOMATIC)
            ->assertSee('Client ID')
            ->assertSee('Store ID');
    }

    public function test_the_usual_couriers_are_added_as_names_not_switched_on(): void
    {
        Livewire::test(CourierIndex::class)->call('addSuggested');

        $pathao = Courier::where('name', 'Pathao Courier')->firstOrFail();

        // It knows which module it could use, but nobody has handed it any
        // account details, so it is used by hand until they do.
        $this->assertSame('pathao', $pathao->driver);
        $this->assertFalse($pathao->isAutomatic());
        $this->assertTrue($pathao->canBeAutomatic());

        // And one we cannot talk to is just a name.
        $this->assertNull(Courier::where('name', 'Sundarban Courier')->firstOrFail()->driver);
    }
}
