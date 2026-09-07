<?php

namespace Tests\Feature\Storefront;

use App\Exceptions\OrderRefused;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\DeliveryArea;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Orders\PlaceOrder;
use App\Services\Storefront\Basket;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Checkout: turning a basket into an order, and taking the money.
 *
 * The rules being defended here are the expensive ones. An order is priced
 * from the shop's own prices, not from anything the customer sent. Stock comes
 * off in the same breath as the order is written, or neither happens. A
 * completed order is never rewritten. And bKash may say the same thing twice
 * without it counting twice.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($this->store, $package);
        PackageTemplate::create(['package_id' => $package->id, 'template' => 'grocery']);

        Entitlements::forget();
        Tenancy::set($this->store);
        app(TemplateCatalogue::class)->choose($this->store, 'grocery');
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function sell(string $name, array $with = []): Product
    {
        $created = app(ProductService::class)->create(array_merge([
            'name' => $name, 'regular_price' => '100', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ], $with));

        $product = $created instanceof Product ? $created : $created->product;

        return $product->fresh(['variants.inventory']);
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    protected function cashOnDelivery(): PaymentMethod
    {
        return PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod',
            'is_enabled' => true, 'settings' => ['instructions' => 'Pay the rider.'], 'position' => 1,
        ]);
    }

    protected function bkash(): PaymentMethod
    {
        return PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'bkash', 'is_enabled' => true,
            'credentials' => ['app_key' => 'k', 'app_secret' => 's', 'password' => 'p'],
            'settings' => ['username' => 'u', 'sandbox' => true],
            'position' => 2,
        ]);
    }

    protected function area(string $name, int $chargeMinor): DeliveryArea
    {
        return DeliveryArea::create([
            'tenant_id' => $this->store->id, 'name' => $name,
            'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 15,
            'delivery_charge_minor' => $chargeMinor,
        ]);
    }

    /** @return array{0: string, 1: \App\Models\ProductVariant} */
    protected function shopWithOneThing(): array
    {
        $rice = $this->sell('Basmati rice', ['unit' => 'per kg']);
        $url = $this->shopUrl();

        return [$url, $rice->variants->first()];
    }

    protected function details(array $with = []): array
    {
        return array_merge([
            'name' => 'Jewel Rana',
            'phone' => '01711223344',
            'address' => 'House 4, Road 2, Mirpur',
        ], $with);
    }

    /*
     * ------------------------------------------------------------ the basics
     */

    public function test_an_empty_basket_has_nothing_to_check_out(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/checkout')->assertRedirect($url.'/basket');
    }

    public function test_cash_on_delivery_places_a_real_order_and_takes_the_stock(): void
    {
        $this->cashOnDelivery();
        $this->area('Mirpur', 6000);
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id, 'quantity' => 2]);

        $response = $this->post($url.'/checkout', $this->details([
            'payment' => 'cod',
            'delivery_area_id' => DeliveryArea::withoutGlobalScopes()->first()->id,
        ]));

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $response->assertRedirect($url.'/orders/'.$order->reference.'?token='.$order->view_token);

        // 2 × 100.00 plus 60.00 delivery.
        $this->assertSame(20000, $order->goods_minor);
        $this->assertSame(6000, $order->delivery_minor);
        $this->assertSame(26000, $order->total_minor);
        $this->assertSame('BDT', $order->currency);
        $this->assertSame(Order::STATUS_PLACED, $order->status);
        $this->assertSame(Order::PAYMENT_ON_DELIVERY, $order->payment_status);
        $this->assertSame('Mirpur', $order->delivery_area_name);

        // Five were on the shelf; two are now spoken for.
        $this->assertSame(3, $variant->fresh()->inventory->available);

        // And the shop was told, in the same breath.
        $this->assertDatabaseHas('outbox_events', ['type' => 'order.placed', 'tenant_id' => $this->store->id]);
    }

    public function test_the_basket_is_emptied_once_the_order_holds_the_stock(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod']));

        $this->get($url.'/basket')->assertOk()->assertSee('Nothing in here yet');
    }

    public function test_a_line_keeps_the_name_and_price_it_was_bought_at(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod']));

        // The shopkeeper renames it and doubles the price afterwards.
        Tenancy::set($this->store);
        $variant->product->forceFill(['name' => 'Something else', 'unit' => 'per sack'])->save();
        $variant->forceFill(['price_minor' => 20000])->save();
        Tenancy::forget();

        $line = Order::withoutGlobalScopes()->firstOrFail()->lines()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Basmati rice', $line->name);
        $this->assertSame('per kg', $line->unit);
        $this->assertSame(10000, $line->unit_price_minor);
    }

    /*
     * -------------------------------------------------------- what it refuses
     */

    public function test_an_order_for_more_than_is_on_the_shelf_is_refused_whole(): void
    {
        $this->cashOnDelivery();
        $rice = $this->sell('Basmati rice', ['stock' => 1]);
        $variant = $rice->variants->first();
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id, 'quantity' => 1]);

        // Somebody else takes the last one first.
        Tenancy::set($this->store);
        $variant->inventory->forceFill(['available' => 0])->save();
        Tenancy::forget();

        $this->from($url.'/checkout')
            ->post($url.'/checkout', $this->details(['payment' => 'cod']))
            ->assertRedirect($url.'/checkout')
            ->assertSessionHasErrors('basket');

        // Nothing half-written: no order, and no stock taken.
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, $variant->fresh()->inventory->available);
    }

    public function test_a_way_of_paying_the_shop_does_not_offer_is_refused(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);

        $this->from($url.'/checkout')
            ->post($url.'/checkout', $this->details(['payment' => 'stripe']))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_the_customer_cannot_set_their_own_price(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);

        // Everything a hopeful customer might try to send.
        $this->post($url.'/checkout', $this->details([
            'payment' => 'cod',
            'total_minor' => 1, 'goods_minor' => 1, 'delivery_minor' => 0, 'price' => '0.01',
        ]));

        $this->assertSame(10000, Order::withoutGlobalScopes()->firstOrFail()->total_minor);
    }

    /*
     * ------------------------------------------------------------- delivery
     */

    public function test_delivery_is_charged_once_per_order_not_per_item(): void
    {
        $this->cashOnDelivery();
        $area = $this->area('Uttara', 8000);
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id, 'quantity' => 4]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod', 'delivery_area_id' => $area->id]));

        $this->assertSame(8000, Order::withoutGlobalScopes()->firstOrFail()->delivery_minor);
    }

    public function test_a_product_marked_free_delivery_is_delivered_free(): void
    {
        $this->cashOnDelivery();
        $area = $this->area('Uttara', 8000);
        $rice = $this->sell('Basmati rice', ['shipping_charge' => '0']);
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod', 'delivery_area_id' => $area->id]));

        $this->assertSame(0, Order::withoutGlobalScopes()->firstOrFail()->delivery_minor);
    }

    public function test_a_shop_with_no_areas_drawn_charges_nothing_to_deliver(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod']));

        $this->assertSame(0, Order::withoutGlobalScopes()->firstOrFail()->delivery_minor);
    }

    /*
     * ---------------------------------------------------------------- bKash
     */

    public function test_paying_with_bkash_opens_a_payment_and_sends_the_customer_there(): void
    {
        $this->bkash();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok', 'status' => '0000']),
            '*create' => Http::response([
                'paymentID' => 'PAY123', 'bkashURL' => 'https://sandbox.bkash/pay/PAY123', 'statusCode' => '0000',
            ]),
        ]);

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'bkash']))
            ->assertRedirect('https://sandbox.bkash/pay/PAY123');

        $order = Order::withoutGlobalScopes()->firstOrFail();

        // Not an order yet: nobody has paid.
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(Order::PAYMENT_UNPAID, $order->payment_status);

        // But the stock is held, so it cannot be sold twice while they pay.
        $this->assertSame(4, $variant->fresh()->inventory->available);

        $payment = Payment::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame($order->total_minor, $payment->amount_minor);
    }

    public function test_coming_back_from_bkash_paid_makes_it_a_real_order(): void
    {
        $this->bkash();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok', 'status' => '0000']),
            '*create' => Http::response(['paymentID' => 'PAY123', 'bkashURL' => 'https://b/pay', 'statusCode' => '0000']),
            '*execute' => Http::response([
                'paymentID' => 'PAY123', 'trxID' => 'TRX999', 'transactionStatus' => 'Completed',
                'statusCode' => '0000', 'customerMsisdn' => '01711223344',
            ]),
        ]);

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'bkash']));

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $this->get($url.'/payments/bkash/callback?paymentID=PAY123&status=success')
            ->assertRedirect($url.'/orders/'.$order->reference.'?token='.$order->view_token);

        $order->refresh();

        $this->assertSame(Order::STATUS_PLACED, $order->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(4, $variant->fresh()->inventory->available);
    }

    public function test_bkash_telling_us_twice_only_counts_once(): void
    {
        $this->bkash();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok', 'status' => '0000']),
            '*create' => Http::response(['paymentID' => 'PAY123', 'bkashURL' => 'https://b/pay', 'statusCode' => '0000']),
            '*execute' => Http::response([
                'paymentID' => 'PAY123', 'trxID' => 'TRX999', 'transactionStatus' => 'Completed', 'statusCode' => '0000',
            ]),
        ]);

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'bkash']));

        $this->get($url.'/payments/bkash/callback?paymentID=PAY123&status=success');
        $paidAt = Order::withoutGlobalScopes()->firstOrFail()->paid_at;

        // The customer refreshes the page they came back to.
        $this->get($url.'/payments/bkash/callback?paymentID=PAY123&status=success');

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $this->assertEquals($paidAt, $order->paid_at);
        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(1, Payment::withoutGlobalScopes()->count());
        $this->assertSame(4, $variant->fresh()->inventory->available);
    }

    public function test_backing_out_of_bkash_puts_the_stock_back(): void
    {
        $this->bkash();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok', 'status' => '0000']),
            '*create' => Http::response(['paymentID' => 'PAY123', 'bkashURL' => 'https://b/pay', 'statusCode' => '0000']),
        ]);

        $this->post($url.'/basket/add', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post($url.'/checkout', $this->details(['payment' => 'bkash']));

        $this->assertSame(3, $variant->fresh()->inventory->available);

        $this->get($url.'/payments/bkash/callback?paymentID=PAY123&status=cancel');

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(5, $variant->fresh()->inventory->available);
    }

    /*
     * -------------------------------------------------- looking at the order
     */

    public function test_an_order_needs_its_secret_to_be_opened(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod']));

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $this->get($url.'/orders/'.$order->reference.'?token='.$order->view_token)
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('Basmati rice');

        // The order number alone is not enough.
        $this->get($url.'/orders/'.$order->reference)->assertNotFound();
        $this->get($url.'/orders/'.$order->reference.'?token=guessing')->assertNotFound();
    }

    public function test_one_shop_cannot_place_an_order_in_another_shops_name(): void
    {
        $this->cashOnDelivery();
        [$url, $variant] = $this->shopWithOneThing();

        // A second shop with its own thing for sale.
        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 50])->create());

        $theirVariantId = Tenancy::run($other, function () {
            Entitlements::forget();
            $created = app(ProductService::class)->create([
                'name' => 'Their rice', 'regular_price' => '10', 'stock' => 5, 'status' => Product::STATUS_ACTIVE,
            ]);

            return ($created instanceof Product ? $created : $created->product)->fresh()->variants->first()->id;
        });

        Entitlements::forget();
        Tenancy::forget();

        // Their product cannot even go in this shop's basket.
        $this->post($url.'/basket/add', ['variant_id' => $theirVariantId]);
        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);
        $this->post($url.'/checkout', $this->details(['payment' => 'cod']));

        $order = Order::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->store->id, $order->tenant_id);
        $this->assertSame(1, $order->lines()->withoutGlobalScopes()->count());
        $this->assertSame(10000, $order->total_minor);
    }

    /*
     * -------------------------------------------------------------- the rule
     */

    public function test_placing_an_order_with_nothing_in_the_basket_throws(): void
    {
        // A basket lives in the session, so give this bare request one.
        request()->setLaravelSession(app('session.store'));

        $this->expectException(OrderRefused::class);

        app(PlaceOrder::class)->place(app(Basket::class), $this->details(), 'cod', null);
    }

    public function test_no_outbox_row_is_written_when_the_order_fails(): void
    {
        $this->cashOnDelivery();
        $rice = $this->sell('Basmati rice', ['stock' => 0]);
        $url = $this->shopUrl();
        Tenancy::forget();

        // Force it into the basket past the sold-out guard, then check out.
        $this->withSession(['basket.'.$this->store->id => [$rice->variants->first()->id => 1]])
            ->post($url.'/checkout', $this->details(['payment' => 'cod']));

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, OutboxEvent::withoutGlobalScopes()->where('type', 'order.placed')->count());
    }
}
