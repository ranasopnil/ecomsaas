<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ProductForm;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitTest extends TestCase
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

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_what_a_product_costs_is_saved_with_it(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Panjabi')
            ->set('price', '1450')
            ->set('cost_price', '900')
            ->set('stock', '10')
            ->call('save')
            ->assertHasNoErrors();

        $variant = Product::firstOrFail()->defaultVariant();

        $this->assertSame(90000, $variant->cost_price_minor);
        $this->assertSame(55000, $variant->profitMinor());
        $this->assertSame(37.9, $variant->marginPercent());
    }

    public function test_the_dashboard_adds_up_what_the_stock_cost_and_what_it_would_make(): void
    {
        $service = app(ProductService::class);
        $service->create(['name' => 'A', 'price' => '1000', 'cost_price' => '600', 'stock' => 10]);
        $service->create(['name' => 'B', 'price' => '500', 'cost_price' => '300', 'stock' => 4]);

        Livewire::test(Dashboard::class)
            // 10 x 600 + 4 x 300 = 7200
            ->assertSee('7200.00')
            // 10 x 1000 + 4 x 500 = 12000
            ->assertSee('12000.00')
            // profit 4800
            ->assertSee('4800.00');
    }

    public function test_things_with_no_cost_entered_are_left_out_and_said_so(): void
    {
        $service = app(ProductService::class);
        $service->create(['name' => 'Costed', 'price' => '1000', 'cost_price' => '600', 'stock' => 2]);
        $service->create(['name' => 'Not costed', 'price' => '900', 'stock' => 5]);

        Livewire::test(Dashboard::class)
            ->assertSee('1200.00')
            ->assertSee('have no cost entered yet');
    }

    public function test_selling_below_cost_is_pointed_out(): void
    {
        app(ProductService::class)->create(['name' => 'Loss maker', 'price' => '400', 'cost_price' => '600', 'stock' => 3]);

        Livewire::test(Dashboard::class)->assertSee('you would lose money');
    }

    public function test_each_combination_can_have_its_own_cost(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'Shoes', 'price' => '2000', 'cost_price' => '1200']);
        $product = $products->setOptions($product, [['name' => 'Size', 'values' => ['40', '41']]]);

        $variants = $product->variants;

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set("variantRows.{$variants[0]->id}.cost", '1300')
            ->set("variantRows.{$variants[1]->id}.cost", '1250')
            ->call('saveVariants')
            ->assertHasNoErrors();

        $this->assertSame(130000, $variants[0]->fresh()->cost_price_minor);
        $this->assertSame(125000, $variants[1]->fresh()->cost_price_minor);
    }

    public function test_a_shopkeeper_never_sees_another_shops_costs(): void
    {
        app(ProductService::class)->create(['name' => 'Secret margin', 'price' => '1000', 'cost_price' => '100', 'stock' => 50]);

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Tenancy::run($other, function () use ($other) {
            $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));

            Livewire::test(Dashboard::class)->assertDontSee('45000.00');
        });
    }
}
