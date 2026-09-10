<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
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
            ->set('regular_price', '1450')
            ->set('cost_price', '900')
            ->set('stock', '10')
            ->call('save')
            ->assertHasNoErrors();

        $variant = Product::firstOrFail()->defaultVariant();

        $this->assertSame(90000, $variant->cost_price_minor);
        $this->assertSame(55000, $variant->profitMinor());
        $this->assertSame(37.9, $variant->marginPercent());
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
}
