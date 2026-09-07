<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\OrderIndex;
use App\Livewire\Admin\OrderShow;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Orders\PlaceOrder;
use App\Services\Storefront\Basket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shopkeeper's own view of what has been ordered.
 */
class OrderScreenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod', 'is_enabled' => true, 'position' => 0,
        ]);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function anOrder(string $customer = 'Jewel Rana'): Order
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
        $basket->add($product->fresh()->variants->first(), 2);

        return app(PlaceOrder::class)->place(
            $basket,
            ['name' => $customer, 'phone' => '01711223344', 'address' => 'House 4, Mirpur'],
            'cod',
            $area,
        );
    }

    public function test_the_shopkeeper_sees_the_orders_that_came_in(): void
    {
        $order = $this->anOrder();

        Livewire::test(OrderIndex::class)
            ->assertSee($order->reference)
            ->assertSee('Jewel Rana')
            ->assertSee('Mirpur')
            // 2 × 100.00 plus 60.00 delivery.
            ->assertSee('260.00');
    }

    public function test_orders_can_be_found_by_name_or_number(): void
    {
        $mine = $this->anOrder('Jewel Rana');
        $theirs = $this->anOrder('Someone Else');

        Livewire::test(OrderIndex::class)
            ->set('search', 'Someone')
            ->assertSee($theirs->reference)
            ->assertDontSee($mine->reference);

        Livewire::test(OrderIndex::class)
            ->set('search', $mine->reference)
            ->assertSee($mine->reference)
            ->assertDontSee($theirs->reference);
    }

    public function test_one_order_shows_what_was_agreed_and_where_it_goes(): void
    {
        $order = $this->anOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSee($order->reference)
            ->assertSee('Basmati rice')
            ->assertSee('per kg')
            ->assertSee('House 4, Mirpur')
            ->assertSee('01711223344')
            ->assertSee('Cash on delivery')
            ->assertSee('260.00');
    }

    public function test_a_shopkeeper_cannot_open_another_shops_order(): void
    {
        $order = $this->anOrder();

        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($other);
        $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));

        // The tenant scope makes the other shop's order simply not exist.
        $this->assertNull(Order::find($order->id));
    }
}
