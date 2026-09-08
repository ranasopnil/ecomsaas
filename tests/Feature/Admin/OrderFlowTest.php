<?php

namespace Tests\Feature\Admin;

use App\Exceptions\OrderStepRefused;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\CourierIndex;
use App\Livewire\Admin\OrderIndex;
use App\Livewire\Admin\OrderShow;
use App\Models\Courier;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OutboxEvent;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Moving an order along, from a customer pressing pay to a parcel arriving.
 *
 * A shopkeeper approves an order, packs it, hands it to a courier and says
 * what happened. These prove the road can only be walked in order, that every
 * step is written down with who took it and why, and that turning an order
 * down puts its stock back without touching anybody's money.
 */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected User $shopkeeper;

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

        $this->shopkeeper = User::factory()->create([
            'tenant_id' => $this->store->id, 'name' => 'Jewel Rana',
        ]);

        $this->actingAs($this->shopkeeper);
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

    protected function anOrder(int $quantity = 2): Order
    {
        $created = app(ProductService::class)->create([
            'name' => 'Basmati rice', 'regular_price' => '100', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE, 'unit' => 'per kg',
        ]);

        $product = $created instanceof Product ? $created : $created->product;

        $area = DeliveryArea::firstOrCreate(
            ['tenant_id' => $this->store->id, 'name' => 'Mirpur'],
            ['latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 10, 'delivery_charge_minor' => 6000],
        );

        request()->setLaravelSession(app('session.store'));

        $basket = app(Basket::class);
        $basket->add($product->fresh()->variants->first(), $quantity);

        return app(PlaceOrder::class)->place(
            $basket,
            ['name' => 'A Shopper', 'phone' => '01711223344', 'address' => 'House 4, Mirpur'],
            'cod',
            $area,
        );
    }

    protected function courier(string $name = 'Pathao Courier'): Courier
    {
        return Courier::create([
            'tenant_id' => $this->store->id,
            'name' => $name,
            'tracking_url' => 'https://merchant.pathao.com/tracking?consignment_id={code}',
        ]);
    }

    protected function stockLeft(Order $order): int
    {
        return $order->lines->first()->variant->inventory->available;
    }

    /*
     * ---------------------------------------------------------------
     * The road, and only in order
     * ---------------------------------------------------------------
     */

    public function test_a_new_order_can_only_be_approved_or_rejected(): void
    {
        $order = $this->anOrder();

        $this->assertSame(
            [Order::STATUS_APPROVED, Order::STATUS_CANCELLED],
            array_column($this->flow()->steps($order), 'status'),
        );
    }

    public function test_an_order_cannot_skip_from_new_to_delivered(): void
    {
        $order = $this->anOrder();

        $this->expectException(OrderStepRefused::class);

        $this->flow()->apply($order, Order::STATUS_DELIVERED);
    }

    public function test_a_shopkeeper_walks_an_order_all_the_way_to_delivered(): void
    {
        $order = $this->anOrder();
        $courier = $this->courier();

        $order = $this->flow()->apply($order, Order::STATUS_APPROVED);
        $this->assertSame(Order::STATUS_APPROVED, $order->status);
        $this->assertNotNull($order->approved_at);

        $order = $this->flow()->apply($order, Order::STATUS_PROCESSING);
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);

        $order = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'tracking_code' => 'PTH-99887766',
        ]);
        $this->assertSame(Order::STATUS_HANDED_OVER, $order->status);
        $this->assertSame('Pathao Courier', $order->courier_name);
        $this->assertSame('PTH-99887766', $order->tracking_code);
        $this->assertNotNull($order->handed_over_at);

        $order = $this->flow()->apply($order, Order::STATUS_DELIVERED);
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);

        // Nothing follows delivered.
        $this->assertSame([], $this->flow()->steps($order));

        // Every step is written down, in the order it happened.
        $this->assertSame(
            [Order::STATUS_APPROVED, Order::STATUS_PROCESSING, Order::STATUS_HANDED_OVER, Order::STATUS_DELIVERED],
            $order->events()->pluck('to_status')->all(),
        );
    }

    public function test_handing_over_needs_a_courier_chosen(): void
    {
        $order = $this->flow()->apply(
            $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
            Order::STATUS_PROCESSING,
        );

        $this->expectExceptionMessage('Choose which courier');

        $this->flow()->apply($order, Order::STATUS_HANDED_OVER);
    }

    public function test_a_courier_from_another_shop_cannot_be_chosen(): void
    {
        $order = $this->flow()->apply(
            $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
            Order::STATUS_PROCESSING,
        );

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        $theirs = Tenancy::run($other, fn () => Courier::create([
            'tenant_id' => $other->id, 'name' => 'Their own man',
        ]));

        $this->expectExceptionMessage('Choose which courier');

        $this->flow()->apply($order, Order::STATUS_HANDED_OVER, ['courier_id' => $theirs->id]);
    }

    /*
     * ---------------------------------------------------------------
     * Saying why
     * ---------------------------------------------------------------
     */

    public function test_turning_an_order_down_needs_a_reason(): void
    {
        $order = $this->anOrder();

        $this->expectExceptionMessage('Say why');

        $this->flow()->apply($order, Order::STATUS_CANCELLED);
    }

    public function test_rejecting_a_new_order_puts_the_stock_back(): void
    {
        $order = $this->anOrder(quantity: 2);
        $order->load('lines.variant.inventory');

        // Five on the shelf, two held by this order.
        $this->assertSame(3, $this->stockLeft($order));

        $rejected = $this->flow()->apply($order, Order::STATUS_CANCELLED, [
            'reason' => 'Out of stock at the warehouse',
        ]);

        $this->assertSame(Order::STATUS_CANCELLED, $rejected->status);
        $this->assertSame('Out of stock at the warehouse', $rejected->cancelled_reason);

        $rejected->load('lines.variant.inventory');
        $this->assertSame(5, $this->stockLeft($rejected));
    }

    public function test_a_parcel_that_did_not_arrive_is_recorded_with_the_reason(): void
    {
        $order = $this->flow()->apply(
            $this->flow()->apply(
                $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
                Order::STATUS_PROCESSING,
            ),
            Order::STATUS_HANDED_OVER,
            ['courier_id' => $this->courier()->id],
        );

        $failed = $this->flow()->apply($order, Order::STATUS_NOT_DELIVERED, [
            'reason' => 'Nobody at home, phone switched off',
        ]);

        $this->assertSame(Order::STATUS_NOT_DELIVERED, $failed->status);
        $this->assertSame('Nobody at home, phone switched off', $failed->not_delivered_reason);

        // The history says so too, in the shopkeeper's own words.
        $this->assertSame(
            'Nobody at home, phone switched off',
            $failed->events()->get()->last()->note,
        );

        // It can go out again, or be given up on.
        $this->assertSame(
            [Order::STATUS_HANDED_OVER, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
            array_column($this->flow()->steps($failed), 'status'),
        );
    }

    public function test_a_parcel_that_came_back_can_go_out_again(): void
    {
        $courier = $this->courier();

        $order = $this->flow()->apply(
            $this->flow()->apply(
                $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
                Order::STATUS_PROCESSING,
            ),
            Order::STATUS_HANDED_OVER,
            ['courier_id' => $courier->id],
        );

        $order = $this->flow()->apply($order, Order::STATUS_NOT_DELIVERED, ['reason' => 'Nobody at home']);
        $order = $this->flow()->apply($order, Order::STATUS_HANDED_OVER, [
            'courier_id' => $courier->id, 'tracking_code' => 'PTH-SECONDTRY',
        ]);

        $this->assertSame(Order::STATUS_HANDED_OVER, $order->status);
        $this->assertSame('PTH-SECONDTRY', $order->tracking_code);

        // The old reason is cleared, but the history still holds it.
        $this->assertNull($order->not_delivered_reason);
        $this->assertSame(1, $order->events()->where('to_status', Order::STATUS_NOT_DELIVERED)->count());
    }

    /*
     * ---------------------------------------------------------------
     * The history
     * ---------------------------------------------------------------
     */

    public function test_every_step_says_who_took_it_and_when(): void
    {
        $order = $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED);

        $event = $order->events()->first();

        $this->assertSame(Order::STATUS_PLACED, $event->from_status);
        $this->assertSame(Order::STATUS_APPROVED, $event->to_status);
        $this->assertSame($this->shopkeeper->id, $event->user_id);
        $this->assertSame('Jewel Rana', $event->user_name);
        $this->assertSame('Jewel Rana', $event->byWhom());
    }

    public function test_every_step_is_written_to_the_outbox_with_the_order(): void
    {
        $order = $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED);

        // Rule seven: the follow-up work is written with the change, not left
        // to a queue that could lose it.
        $this->assertSame(1, OutboxEvent::where('type', 'order.approved')->count());

        $this->flow()->apply($order, Order::STATUS_CANCELLED, ['reason' => 'Changed their mind']);

        $this->assertSame(1, OutboxEvent::where('type', 'order.cancelled')->count());
    }

    public function test_two_people_moving_the_same_order_at_once_does_not_move_it_twice(): void
    {
        $order = $this->anOrder();

        // Both people opened the order while it was new.
        $asSeenByTheSecond = Order::find($order->id);

        $this->flow()->apply($order, Order::STATUS_APPROVED);

        $this->expectExceptionMessage('Somebody moved this order');

        $this->flow()->apply($asSeenByTheSecond, Order::STATUS_CANCELLED, ['reason' => 'Out of stock']);
    }

    /*
     * ---------------------------------------------------------------
     * The screens
     * ---------------------------------------------------------------
     */

    public function test_the_screens_open_from_the_dashboard(): void
    {
        $order = $this->anOrder();

        $hostname = Tenancy::run($this->store, fn () => \App\Models\Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        $this->get('https://'.$hostname.'/admin/orders')->assertOk()->assertSee('Needs you');
        $this->get('https://'.$hostname.'/admin/orders/'.$order->id)->assertOk()->assertSee('What happens next');
        $this->get('https://'.$hostname.'/admin/couriers')->assertOk()->assertSee('Couriers');
    }

    public function test_the_order_screen_approves_in_one_press(): void
    {
        $order = $this->anOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->call('choose', Order::STATUS_APPROVED)
            ->assertDispatched('toast');

        $this->assertSame(Order::STATUS_APPROVED, $order->fresh()->status);
    }

    public function test_the_order_screen_asks_why_before_rejecting(): void
    {
        $order = $this->anOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            // Choosing to reject opens the form; it does not reject.
            ->call('choose', Order::STATUS_CANCELLED)
            ->assertSet('step', Order::STATUS_CANCELLED)
            ->assertSee('Why are you turning it down?');

        $this->assertSame(Order::STATUS_PLACED, $order->fresh()->status);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('choose', Order::STATUS_CANCELLED)
            ->set('reason', 'Cannot reach that address')
            ->call('take', Order::STATUS_CANCELLED);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame('Cannot reach that address', $order->fresh()->cancelled_reason);
    }

    public function test_the_order_screen_will_not_reject_with_nothing_typed(): void
    {
        $order = $this->anOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('choose', Order::STATUS_CANCELLED)
            ->call('take', Order::STATUS_CANCELLED)
            ->assertHasErrors('step');

        $this->assertSame(Order::STATUS_PLACED, $order->fresh()->status);
    }

    public function test_the_orders_list_groups_them_by_what_has_to_happen_next(): void
    {
        $new = $this->anOrder();
        $approved = $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED);

        Livewire::test(OrderIndex::class)
            ->assertSee('Needs you')
            ->assertSee($new->reference)
            ->assertSee($approved->reference)
            ->call('showing', 'new')
            ->assertSee($new->reference)
            ->assertDontSee($approved->reference)
            ->call('showing', 'approved')
            ->assertSee($approved->reference)
            ->assertDontSee($new->reference);
    }

    public function test_the_history_is_shown_on_the_order(): void
    {
        $order = $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED);

        Livewire::test(OrderShow::class, ['order' => $order->fresh()])
            ->assertSee('History')
            ->assertSee('Approved')
            ->assertSee('Jewel Rana');
    }

    /*
     * ---------------------------------------------------------------
     * Couriers
     * ---------------------------------------------------------------
     */

    public function test_a_shop_can_add_the_couriers_most_shops_here_use(): void
    {
        Livewire::test(CourierIndex::class)
            ->assertSee('No couriers yet')
            ->call('addSuggested')
            ->assertDispatched('toast');

        $this->assertGreaterThan(0, Courier::count());
        $this->assertTrue(Courier::where('name', 'Pathao Courier')->exists());
    }

    public function test_a_shop_can_name_its_own_courier(): void
    {
        Livewire::test(CourierIndex::class)
            ->call('add')
            ->set('name', 'My own delivery man')
            ->set('phone', '01711000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Courier::where('name', 'My own delivery man')->exists());
    }

    public function test_a_courier_that_has_carried_parcels_cannot_be_removed(): void
    {
        $courier = $this->courier();

        $this->flow()->apply(
            $this->flow()->apply(
                $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
                Order::STATUS_PROCESSING,
            ),
            Order::STATUS_HANDED_OVER,
            ['courier_id' => $courier->id],
        );

        Livewire::test(CourierIndex::class)
            ->call('delete', $courier->id)
            ->assertDispatched('toast');

        $this->assertTrue(Courier::whereKey($courier->id)->exists());
    }

    public function test_a_parcel_can_be_followed_on_the_couriers_own_page(): void
    {
        $courier = $this->courier();

        $this->assertSame(
            'https://merchant.pathao.com/tracking?consignment_id=PTH-1234',
            $courier->trackingUrlFor('PTH-1234'),
        );

        // Nothing to follow without a consignment number, and nothing that is
        // not an ordinary web address is ever put in a link.
        $this->assertNull($courier->trackingUrlFor(''));
        $this->assertNull(Courier::create([
            'tenant_id' => $this->store->id, 'name' => 'Odd one', 'tracking_url' => 'javascript:alert(1)',
        ])->trackingUrlFor('X1'));
    }

    /*
     * ---------------------------------------------------------------
     * One shop's orders are its own
     * ---------------------------------------------------------------
     */

    public function test_one_shop_can_never_move_another_shops_order(): void
    {
        $mine = $this->anOrder();

        $other = Tenant::factory()->create(['currency' => 'BDT']);

        Tenancy::run($other, function () use ($mine) {
            $this->assertNull(Order::find($mine->id));
            $this->assertSame(0, OrderEvent::count());
        });
    }
}
