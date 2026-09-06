<?php

namespace Tests\Feature\Catalogue;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\ProductForm;
use App\Models\Package;
use App\Models\ProductImage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ImageService;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle(
            $this->store,
            Package::factory()->allowing(['products' => 50, 'storage_mb' => 100])->create()
        );

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

    public function test_a_photo_can_be_added_to_a_product(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Panjabi', 'price' => '1450']);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('newPhotos', [UploadedFile::fake()->image('front.jpg', 900, 900)])
            ->assertHasNoErrors();

        $image = ProductImage::firstOrFail();

        $this->assertSame($product->id, $image->product_id);
        $this->assertTrue($image->is_primary);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_a_very_large_photo_is_shrunk_on_the_way_in(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Big Picture', 'price' => '100']);

        app(ImageService::class)->store($product, UploadedFile::fake()->image('huge.jpg', 4000, 3000));

        $image = ProductImage::firstOrFail();

        $this->assertLessThanOrEqual(ImageService::MAX_EDGE, $image->width);
        $this->assertNotNull($image->thumbnail_path);
        Storage::disk('public')->assertExists($image->thumbnail_path);
    }

    public function test_a_photo_can_belong_to_one_combination(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'T Shirt', 'price' => '500']);
        $product = $products->setOptions($product, [['name' => 'Colour', 'values' => ['Red', 'Blue']]]);

        $red = $product->variants->first();

        app(ImageService::class)->store($product, UploadedFile::fake()->image('red.jpg'), $red->id);

        $this->assertSame($red->id, ProductImage::firstOrFail()->product_variant_id);
        $this->assertSame(1, $red->fresh()->images()->count());
    }

    public function test_a_combination_falls_back_to_the_products_main_photo(): void
    {
        $products = app(ProductService::class);
        $product = $products->create(['name' => 'Cap', 'price' => '300']);
        $product = $products->setOptions($product, [['name' => 'Size', 'values' => ['S', 'M']]]);

        app(ImageService::class)->store($product, UploadedFile::fake()->image('cap.jpg'));

        $variant = $product->fresh(['variants', 'images'])->variants->first();

        $this->assertNotNull($variant->displayImage());
        $this->assertNull($variant->displayImage()->product_variant_id);
    }

    public function test_removing_the_main_photo_promotes_the_next_one(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Two Photos', 'price' => '100']);
        $images = app(ImageService::class);

        $first = $images->store($product, UploadedFile::fake()->image('one.jpg'));
        $second = $images->store($product, UploadedFile::fake()->image('two.jpg'));

        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->is_primary);

        $images->delete($first);

        $this->assertTrue($second->fresh()->is_primary);
        Storage::disk('public')->assertMissing($first->path);
    }

    public function test_the_plan_limits_how_much_room_photos_may_take(): void
    {
        $tiny = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($tiny, Package::factory()->allowing(['products' => 10, 'storage_mb' => 0])->create());
        Entitlements::forget();

        Tenancy::run($tiny, function () {
            $product = app(ProductService::class)->create(['name' => 'No Room', 'price' => '100']);

            $this->expectException(LimitReached::class);

            app(ImageService::class)->store($product, UploadedFile::fake()->image('photo.jpg', 800, 800));
        });
    }

    public function test_one_shop_never_sees_another_shops_photos(): void
    {
        $product = app(ProductService::class)->create(['name' => 'Mine', 'price' => '100']);
        app(ImageService::class)->store($product, UploadedFile::fake()->image('mine.jpg'));

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Tenancy::run($other, fn () => $this->assertSame(0, ProductImage::count()));
    }
}
