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

class ProductDetailsTest extends TestCase
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

    public function test_a_discount_price_is_what_the_customer_pays(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Eid Panjabi')
            ->set('regular_price', '2000')
            ->set('discount_price', '1500')
            ->call('save')
            ->assertHasNoErrors();

        $variant = Product::firstOrFail()->defaultVariant();

        $this->assertSame(150000, $variant->price_minor);
        $this->assertSame(200000, $variant->compare_at_price_minor);
        $this->assertTrue($variant->isDiscounted());
    }

    public function test_a_discount_that_is_not_lower_is_refused(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Odd Pricing')
            ->set('regular_price', '1000')
            ->set('discount_price', '1200')
            ->call('save')
            ->assertHasErrors('discount_price');

        $this->assertSame(0, Product::count());
    }

    public function test_removing_the_discount_puts_the_regular_price_back(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Was On Sale',
            'regular_price' => '2000',
            'discount_price' => '1500',
        ]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('regular_price', '2000.00')
            ->assertSet('discount_price', '1500.00')
            ->set('discount_price', '')
            ->call('save')
            ->assertHasNoErrors();

        $variant = $product->fresh('variants')->defaultVariant();

        $this->assertSame(200000, $variant->price_minor);
        $this->assertNull($variant->compare_at_price_minor);
    }

    public function test_publishing_is_a_plain_yes_or_no(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Not Ready')
            ->set('regular_price', '100')
            ->set('is_published', false)
            ->call('save');

        $this->assertSame(Product::STATUS_DRAFT, Product::firstOrFail()->status);

        Livewire::test(ProductForm::class, ['product' => Product::firstOrFail()])
            ->set('is_published', true)
            ->call('save');

        $this->assertSame(Product::STATUS_ACTIVE, Product::firstOrFail()->status);
        $this->assertSame(1, Product::onSale()->count());
    }

    public function test_the_short_description_and_search_words_are_kept(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Cotton Panjabi')
            ->set('regular_price', '1450')
            ->set('short_description', 'Soft cotton panjabi, made in Dhaka.')
            ->set('tags', 'panjabi, cotton, eid,  panjabi ')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::firstOrFail();

        $this->assertSame('Soft cotton panjabi, made in Dhaka.', $product->short_description);
        $this->assertSame(['panjabi', 'cotton', 'eid'], $product->tags);
    }

    public function test_the_shopkeeper_says_what_the_price_is_the_price_of(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Basmati rice')
            ->set('regular_price', '620')
            ->set('unit', 'per kg')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('per kg', Product::firstOrFail()->unit);
    }

    public function test_the_unit_can_be_changed_and_taken_away_again(): void
    {
        $created = app(ProductService::class)->create([
            'name' => 'Farm eggs', 'regular_price' => '155', 'unit' => 'per dozen',
        ]);
        $product = $created instanceof Product ? $created : $created->product;

        $this->assertSame('per dozen', $product->unit);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('unit', 'per tray')
            ->call('save');

        $this->assertSame('per tray', $product->fresh()->unit);

        Livewire::test(ProductForm::class, ['product' => $product->fresh()])
            ->set('unit', '')
            ->call('save');

        $this->assertNull($product->fresh()->unit);
    }

    public function test_markup_typed_into_the_unit_is_reduced_to_words(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Safe')
            ->set('regular_price', '100')
            ->set('unit', '<b>per kg</b>')
            ->call('save');

        $this->assertSame('per kg', Product::firstOrFail()->unit);
    }

    public function test_markup_typed_into_the_short_description_is_reduced_to_words(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Safe')
            ->set('regular_price', '100')
            ->set('short_description', '<script>alert(1)</script>Just <b>cotton</b>')
            ->call('save');

        $this->assertSame('alert(1)Just cotton', Product::firstOrFail()->short_description);
    }

    public function test_weight_size_and_delivery_charge_are_saved(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Boxed Thing')
            ->set('regular_price', '500')
            ->set('shipping_charge', '60')
            ->set('weight_grams', '1250')
            ->set('length_mm', '300')
            ->set('width_mm', '200')
            ->set('height_mm', '50')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::firstOrFail();
        $variant = $product->defaultVariant();

        $this->assertSame(6000, $product->shipping_charge_minor);
        $this->assertSame(1250, $variant->weight_grams);
        $this->assertSame('1.25 kg', $variant->weightLabel());
        $this->assertSame('30 × 20 × 5 cm', $variant->dimensionsLabel());
    }

    public function test_free_delivery_is_zero_and_not_the_same_as_empty(): void
    {
        $free = app(ProductService::class)->create(['name' => 'Free', 'regular_price' => '100', 'shipping_charge' => '0']);
        $usual = app(ProductService::class)->create(['name' => 'Usual', 'regular_price' => '100']);

        $this->assertSame(0, $free->shipping_charge_minor);
        $this->assertNull($usual->shipping_charge_minor);
    }

    public function test_a_youtube_link_is_accepted_and_anything_else_is_not(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'With Video')
            ->set('regular_price', '100')
            ->set('video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('dQw4w9WgXcQ', Product::firstOrFail()->youtubeId());

        Livewire::test(ProductForm::class, ['product' => Product::firstOrFail()])
            ->set('video_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('video_url');
    }

    public function test_a_short_youtube_link_works_too(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Short Link',
            'regular_price' => '100',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->assertSame('dQw4w9WgXcQ', $product->youtubeId());
    }

    public function test_each_variant_can_have_its_own_discount_and_weight(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'Shirt', 'regular_price' => '1000']);
        $product = $products->setOptions($product, [['name' => 'Size', 'values' => ['M', 'L']]]);

        $variants = $product->variants;

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set("variantRows.{$variants[0]->id}.regular", '1000')
            ->set("variantRows.{$variants[0]->id}.discount", '800')
            ->set("variantRows.{$variants[0]->id}.weight", '400')
            ->set("variantRows.{$variants[1]->id}.regular", '1100')
            ->call('saveVariants')
            ->assertHasNoErrors();

        $medium = $variants[0]->fresh();

        $this->assertSame(80000, $medium->price_minor);
        $this->assertSame(100000, $medium->compare_at_price_minor);
        $this->assertSame(400, $medium->weight_grams);
        $this->assertSame(110000, $variants[1]->fresh()->price_minor);
    }

    public function test_a_variant_discount_that_is_not_lower_is_refused(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'Shirt', 'regular_price' => '1000']);
        $product = $products->setOptions($product, [['name' => 'Size', 'values' => ['M']]]);

        $variant = $product->variants->first();

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set("variantRows.{$variant->id}.regular", '1000')
            ->set("variantRows.{$variant->id}.discount", '1000')
            ->call('saveVariants')
            ->assertHasErrors();

        $this->assertNull($variant->fresh()->compare_at_price_minor);
    }
}
