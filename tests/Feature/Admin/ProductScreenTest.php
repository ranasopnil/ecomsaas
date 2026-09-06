<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\ProductForm;
use App\Livewire\Admin\ProductIndex;
use App\Livewire\Admin\StockIndex;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\InventoryService;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductScreenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 3])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id, 'role' => User::ROLE_OWNER]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_a_shopkeeper_can_add_a_product(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Cotton Panjabi')
            ->set('description', 'Soft cotton, made in Dhaka.')
            ->set('regular_price', '1450.50')
            ->set('sku', 'PANJ-1')
            ->set('stock', '12')
            ->set('is_published', true)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::firstOrFail();

        $this->assertSame('Cotton Panjabi', $product->name);
        $this->assertSame('cotton-panjabi', $product->slug);
        $this->assertSame(145050, $product->defaultVariant()->price_minor);
        $this->assertSame(12, $product->defaultVariant()->inventory->available);
    }

    public function test_the_form_says_plainly_when_the_plan_is_full(): void
    {
        $service = app(ProductService::class);
        $service->create(['name' => 'One', 'price' => '10']);
        $service->create(['name' => 'Two', 'price' => '10']);
        $service->create(['name' => 'Three', 'price' => '10']);

        Livewire::test(ProductForm::class)
            ->set('name', 'Four')
            ->set('regular_price', '10')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(3, Product::count());
    }

    public function test_editing_a_product_changes_its_price_and_stock(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Mug', 'price' => '250', 'stock' => 5]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('regular_price', '250.00')
            ->assertSet('stock', '5')
            ->set('regular_price', '299.99')
            ->set('stock', '20')
            ->call('save')
            ->assertHasNoErrors();

        $variant = $product->fresh('variants')->defaultVariant();

        $this->assertSame(29999, $variant->price_minor);
        $this->assertSame(20, $variant->inventory->fresh()->available);

        // The change to stock is written down, not silently applied.
        $this->assertTrue(InventoryMovement::where('quantity_change', 15)->exists());
    }

    public function test_adding_choices_creates_a_line_for_each_combination(): void
    {
        $product = app(ProductService::class)->create(['name' => 'T Shirt', 'price' => '500']);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->call('addOption')
            ->set('options.0.name', 'Size')
            ->set('options.0.values', 'Small, Medium, Large')
            ->call('save')
            ->assertHasNoErrors();

        $product = $product->fresh('variants');

        $this->assertTrue($product->has_variants);
        $this->assertCount(3, $product->variants);
    }

    public function test_each_combination_can_have_its_own_price_and_stock(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'Sandals', 'price' => '900']);
        $product = $products->setOptions($product, [['name' => 'Size', 'values' => ['38', '39']]]);

        $variants = $product->variants;

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set("variantRows.{$variants[0]->id}.regular", '950')
            ->set("variantRows.{$variants[0]->id}.stock", '4')
            ->set("variantRows.{$variants[1]->id}.regular", '975.50')
            ->set("variantRows.{$variants[1]->id}.stock", '2')
            ->call('saveVariants')
            ->assertHasNoErrors();

        $this->assertSame(95000, $variants[0]->fresh()->price_minor);
        $this->assertSame(97550, $variants[1]->fresh()->price_minor);
        $this->assertSame(4, app(InventoryService::class)->levelFor($variants[0])->fresh()->available);
        $this->assertSame(2, app(InventoryService::class)->levelFor($variants[1])->fresh()->available);
    }

    public function test_a_product_can_be_put_away_and_brought_back(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Seasonal', 'price' => '100', 'status' => Product::STATUS_ACTIVE]);

        Livewire::test(ProductIndex::class)->call('archive', $product->id);
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);
        $this->assertSame(0, Product::onSale()->count());

        Livewire::test(ProductIndex::class)->call('putBackOnSale', $product->id);
        $this->assertSame(Product::STATUS_ACTIVE, $product->fresh()->status);
    }

    public function test_the_product_list_can_be_searched(): void
    {
        $service = app(ProductService::class);
        $service->create(['name' => 'Blue Saree', 'price' => '100']);
        $service->create(['name' => 'Red Kurta', 'price' => '100']);

        Livewire::test(ProductIndex::class)
            ->set('search', 'saree')
            ->assertSee('Blue Saree')
            ->assertDontSee('Red Kurta');
    }

    public function test_a_counted_figure_can_be_entered_on_the_stock_screen(): void
    {
        $variant = app(ProductService::class)
            ->create(['name' => 'Counted', 'price' => '100', 'stock' => 10])
            ->variants->first();

        Livewire::test(StockIndex::class)
            ->set("counted.{$variant->id}", '7')
            ->call('saveCount', $variant->id);

        $this->assertSame(7, app(InventoryService::class)->levelFor($variant)->fresh()->available);
        $this->assertTrue(InventoryMovement::where('quantity_change', -3)->exists());
    }

    public function test_a_product_can_be_filed_under_a_category_from_the_form(): void
    {
        $category = Category::factory()->create(['tenant_id' => $this->store->id, 'name' => 'Shirts']);

        Livewire::test(ProductForm::class)
            ->set('name', 'Linen Shirt')
            ->set('regular_price', '1200')
            ->set('category_ids', [$category->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Product::firstOrFail()->categories()->count());
    }
}
