<?php

namespace Tests\Feature\Catalogue;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function shop(int $productAllowance = 100): Tenant
    {
        $store = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($store, Package::factory()->allowing(['products' => $productAllowance])->create());
        Entitlements::forget($store->id);

        return $store;
    }

    public function test_a_new_product_has_one_thing_that_can_be_sold_and_counted(): void
    {
        $store = $this->shop();

        $product = Tenancy::run($store, fn () => app(ProductService::class)->create([
            'name' => 'Cotton Panjabi',
            'price' => '1450.50',
            'sku' => 'PANJ-1',
            'stock' => 12,
            'status' => Product::STATUS_ACTIVE,
        ]));

        $this->assertSame('cotton-panjabi', $product->slug);
        $this->assertCount(1, $product->variants);

        $variant = $product->variants->first();
        $this->assertSame(145050, $variant->price_minor);
        $this->assertSame('BDT', $variant->currency);
        $this->assertTrue($variant->is_default);

        Tenancy::run($store, function () use ($product) {
            $this->assertSame(12, $product->fresh('variants')->stockOnHand());
        });
    }

    public function test_the_plan_decides_how_many_products_a_shop_may_have(): void
    {
        $store = $this->shop(productAllowance: 2);

        Tenancy::run($store, function () {
            $service = app(ProductService::class);
            $service->create(['name' => 'One', 'price' => '10']);
            $service->create(['name' => 'Two', 'price' => '10']);

            try {
                $service->create(['name' => 'Three', 'price' => '10']);
                $this->fail('The third product should have been refused.');
            } catch (LimitReached $e) {
                $this->assertSame('Your plan allows 2 products. Upgrade your plan to add more.', $e->getMessage());
            }

            $this->assertSame(2, Product::count());
        });
    }

    public function test_two_shops_can_sell_a_product_with_the_same_web_address(): void
    {
        [$shopA, $shopB] = [$this->shop(), $this->shop()];

        $a = Tenancy::run($shopA, fn () => app(ProductService::class)->create(['name' => 'Blue Saree', 'price' => '2000']));
        $b = Tenancy::run($shopB, fn () => app(ProductService::class)->create(['name' => 'Blue Saree', 'price' => '3000']));

        $this->assertSame('blue-saree', $a->slug);
        $this->assertSame('blue-saree', $b->slug);
    }

    public function test_one_shop_never_sees_another_shops_products(): void
    {
        [$shopA, $shopB] = [$this->shop(), $this->shop()];

        Tenancy::run($shopA, fn () => app(ProductService::class)->create(['name' => 'Only Mine', 'price' => '100']));

        Tenancy::run($shopB, function () {
            $this->assertSame(0, Product::count());
            $this->assertSame(0, ProductVariant::count());
        });
    }

    public function test_a_second_product_with_the_same_name_gets_its_own_web_address(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $service = app(ProductService::class);
            $first = $service->create(['name' => 'Red Kurta', 'price' => '100']);
            $second = $service->create(['name' => 'Red Kurta', 'price' => '120']);

            $this->assertSame('red-kurta', $first->slug);
            $this->assertSame('red-kurta-2', $second->slug);
        });
    }

    public function test_choices_produce_one_sellable_line_per_combination(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $service = app(ProductService::class);
            $product = $service->create(['name' => 'T Shirt', 'price' => '500']);

            $product = $service->setOptions($product, [
                ['name' => 'Size', 'values' => ['Small', 'Medium', 'Large']],
                ['name' => 'Colour', 'values' => ['Red', 'Blue']],
            ]);

            $this->assertTrue($product->has_variants);
            $this->assertCount(6, $product->variants);

            $labels = $product->variants->map(fn ($variant) => $variant->choiceLabel())->all();
            $this->assertContains('Small / Red', $labels);
            $this->assertContains('Large / Blue', $labels);

            // Each combination keeps the price the product started with.
            $this->assertSame(50000, $product->variants->first()->price_minor);
        });
    }

    public function test_dropping_a_choice_removes_the_lines_that_used_it(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $service = app(ProductService::class);
            $product = $service->create(['name' => 'Sandals', 'price' => '900']);

            $product = $service->setOptions($product, [['name' => 'Size', 'values' => ['38', '39', '40']]]);
            $this->assertCount(3, $product->variants);

            $product = $service->setOptions($product, [['name' => 'Size', 'values' => ['38', '39']]]);
            $this->assertCount(2, $product->variants);
        });
    }

    public function test_a_product_can_be_filed_under_categories_and_a_brand(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () use ($store) {
            $brand = Brand::factory()->create(['tenant_id' => $store->id]);
            $shirts = Category::factory()->create(['tenant_id' => $store->id, 'name' => 'Shirts']);
            $newIn = Category::factory()->create(['tenant_id' => $store->id, 'name' => 'New in']);

            $product = app(ProductService::class)->create([
                'name' => 'Linen Shirt',
                'price' => '1200',
                'brand_id' => $brand->id,
                'category_ids' => [$shirts->id, $newIn->id],
            ]);

            $this->assertSame($brand->id, $product->brand->id);
            $this->assertCount(2, $product->categories);
            $this->assertSame($store->id, $product->categories->first()->pivot->tenant_id);
        });
    }

    public function test_only_products_on_sale_show_in_the_shop(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $service = app(ProductService::class);
            $service->create(['name' => 'Live One', 'price' => '100', 'status' => Product::STATUS_ACTIVE]);
            $service->create(['name' => 'Still Writing', 'price' => '100', 'status' => Product::STATUS_DRAFT]);

            $this->assertSame(['Live One'], Product::onSale()->pluck('name')->all());
        });
    }
}
