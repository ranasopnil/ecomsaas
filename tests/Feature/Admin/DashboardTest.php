<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\Dashboard;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The first screen a shopkeeper sees.
 *
 * Every figure on it is counted from this shop's own orders, so these prove
 * the counting: that an order which never became one is not counted as a
 * sale, that a shop with nothing yet is told so rather than shown a hopeful
 * trend, and that no shop ever sees another's takings.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'timezone' => 'Asia/Dhaka',
        ]);

        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod', 'is_enabled' => true, 'position' => 0,
        ]);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id, 'name' => 'Md Jewel Rana']));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    /**
     * An order for 260 Taka: two bags of rice at 100, plus 60 delivery.
     */
    protected function anOrder(string $customer = 'Rahim Uddin', string $phone = '01711223344'): Order
    {
        $created = app(ProductService::class)->create([
            'name' => 'Basmati rice '.uniqid(), 'regular_price' => '100', 'stock' => 20,
            'status' => Product::STATUS_ACTIVE, 'unit' => 'per kg',
        ]);

        $product = $created instanceof Product ? $created : $created->product;

        $area = DeliveryArea::firstOrCreate(
            ['tenant_id' => $this->store->id, 'name' => 'Mirpur'],
            ['latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 10, 'delivery_charge_minor' => 6000],
        );

        request()->setLaravelSession(app('session.store'));

        $basket = app(Basket::class);

        // The basket lives in the session, and the checkout is what normally
        // empties it. Each order here starts from an empty one.
        $basket->clear();
        $basket->add($product->fresh()->variants->first(), 2);

        return app(PlaceOrder::class)->place(
            $basket,
            ['name' => $customer, 'phone' => $phone, 'address' => 'House 4, Mirpur'],
            'cod',
            $area,
        );
    }

    /*
     * ---------------------------------------------------------------
     * The four figures
     * ---------------------------------------------------------------
     */

    public function test_it_greets_the_shopkeeper_and_counts_their_own_orders(): void
    {
        $this->anOrder();
        $this->anOrder('Nusrat Jahan', '01799887766');

        Livewire::test(Dashboard::class)
            ->assertSee('Welcome back, Md Jewel Rana!')
            ->assertSee('Total sales')
            // Two orders at 260 each.
            ->assertSee('BDT 520.00')
            ->assertSee('Orders')
            ->assertSee('Customers');
    }

    public function test_an_order_that_never_became_one_is_not_counted_as_a_sale(): void
    {
        $good = $this->anOrder();
        $bad = $this->anOrder('Someone Else', '01700000000');

        app(OrderFlow::class)->apply($bad, Order::STATUS_CANCELLED, ['reason' => 'Out of stock']);

        Livewire::test(Dashboard::class)
            // Only the one that stands.
            ->assertSee('BDT 260.00')
            ->assertDontSee('BDT 520.00');

        $this->assertSame(Order::STATUS_CANCELLED, $bad->fresh()->status);
        $this->assertSame(Order::STATUS_PLACED, $good->fresh()->status);
    }

    public function test_a_shop_with_nothing_yet_is_told_so_rather_than_shown_a_trend(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('BDT 0.00')
            ->assertSee('No orders yet')
            ->assertSee('Nothing sold in these days yet')
            // A first week is not "up 100%".
            ->assertDontSee('↑')
            ->assertDontSee('↓');
    }

    public function test_the_shopkeeper_can_change_how_far_back_the_figures_reach(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('range', '7')
            ->call('setRange', '30')
            ->assertSet('range', '30')
            // Something nobody offered falls back rather than breaking.
            ->call('setRange', 'nonsense')
            ->assertSet('range', '7');
    }

    /*
     * ---------------------------------------------------------------
     * The two charts
     * ---------------------------------------------------------------
     */

    public function test_the_sales_chart_can_be_stretched_without_leaving_the_page(): void
    {
        $this->anOrder();

        Livewire::test(Dashboard::class)
            ->assertSet('chartDays', '14')
            ->assertSee('Last 14 days')
            ->call('setChartDays', '30')
            ->assertSet('chartDays', '30')
            ->assertSee('Last 30 days')
            ->call('setChartDays', 'nonsense')
            ->assertSet('chartDays', '14');
    }

    public function test_the_orders_ring_gathers_the_states_into_the_four_a_shopkeeper_thinks_in(): void
    {
        $new = $this->anOrder();
        $onIts_way = $this->anOrder('Tanvir Ahmed', '01712345678');
        $done = $this->anOrder('Sadia Islam', '01755556666');

        $flow = app(OrderFlow::class);
        $flow->apply($onIts_way, Order::STATUS_APPROVED);
        $flow->apply($flow->apply($done, Order::STATUS_APPROVED), Order::STATUS_PROCESSING);

        Livewire::test(Dashboard::class)
            ->assertSee('Orders overview')
            ->assertSee('Total orders')
            ->assertSee('Delivered')
            ->assertSee('Processing')
            ->assertSee('Pending')
            ->assertSee('Cancelled')
            // Three orders in the ring: one waiting, two being got ready.
            ->assertSee('66.7%')
            ->assertSee('33.3%');

        $this->assertSame(Order::STATUS_PLACED, $new->fresh()->status);
    }

    /*
     * ---------------------------------------------------------------
     * The two tables
     * ---------------------------------------------------------------
     */

    public function test_recent_orders_show_who_bought_and_what_it_came_to(): void
    {
        $order = $this->anOrder('Rahim Uddin', '01711223344');

        Livewire::test(Dashboard::class)
            ->assertSee('Recent orders')
            ->assertSee($order->reference)
            ->assertSee('Rahim Uddin')
            ->assertSee('01711223344')
            ->assertSee('BDT 260.00')
            ->assertSee('New order');
    }

    public function test_top_selling_products_are_counted_from_what_actually_sold(): void
    {
        $this->anOrder();

        Livewire::test(Dashboard::class)
            ->assertSee('Top selling products')
            ->assertSee('Basmati rice')
            // Two of them, at 100 each.
            ->assertSee('2 sold')
            ->assertSee('BDT 200.00');
    }

    /*
     * ---------------------------------------------------------------
     * One shop's takings are its own
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_never_sees_another_shops_takings(): void
    {
        $this->anOrder('Rahim Uddin', '01711223344');

        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($other);
        $this->actingAs(User::factory()->create(['tenant_id' => $other->id, 'name' => 'Somebody Else']));

        Livewire::test(Dashboard::class)
            ->assertSee('BDT 0.00')
            ->assertDontSee('Rahim Uddin')
            ->assertDontSee('BDT 260.00');
    }
}
