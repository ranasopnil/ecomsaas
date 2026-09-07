<?php

namespace Tests\Feature\Catalogue;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\CategoryIndex;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ImageService;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A picture on each category.
 *
 * Some shop fronts show a row of category pictures, so a shopkeeper needs to
 * be able to put one on. Without one, the first letter of the name is shown
 * instead, so a shop is never left with a row of empty holes.
 */
class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $this->package = Package::factory()->allowing(['products' => 50, 'storage_mb' => 100])->create();
        app(SubscribeToPackage::class)->handle($this->store, $this->package);

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

    protected function category(string $name = 'Fruit & veg'): Category
    {
        return Category::create(['name' => $name, 'slug' => Str::slug($name)]);
    }

    public function test_a_shopkeeper_can_put_a_picture_on_a_new_category(): void
    {
        Livewire::test(CategoryIndex::class)
            ->set('name', 'Fruit and veg')
            ->set('photo', UploadedFile::fake()->image('fruit.jpg', 900, 900))
            ->call('save')
            ->assertHasNoErrors();

        $category = Category::where('name', 'Fruit and veg')->firstOrFail();

        $this->assertTrue($category->hasImage());
        Storage::disk('public')->assertExists($category->image_path);
        Storage::disk('public')->assertExists($category->image_thumbnail_path);
        $this->assertGreaterThan(0, $category->image_size_bytes);
    }

    public function test_a_picture_can_be_added_to_a_category_that_already_exists(): void
    {
        $category = $this->category('Dairy');

        Livewire::test(CategoryIndex::class)
            ->call('edit', $category->id)
            ->set('photo', UploadedFile::fake()->image('milk.png', 600, 600))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($category->fresh()->hasImage());
    }

    public function test_replacing_a_picture_does_not_leave_the_old_one_behind(): void
    {
        $images = app(ImageService::class);
        $category = $this->category('Bakery');

        $images->storeForCategory($category, UploadedFile::fake()->image('old.jpg', 800, 800));
        $first = $category->fresh()->image_path;

        $images->storeForCategory($category->fresh(), UploadedFile::fake()->image('new.jpg', 800, 800));
        $second = $category->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_picture_can_be_taken_off_again(): void
    {
        $category = $this->category('Snacks');
        app(ImageService::class)->storeForCategory($category, UploadedFile::fake()->image('crisps.jpg', 500, 500));

        $path = $category->fresh()->image_path;

        Livewire::test(CategoryIndex::class)->call('removeImage', $category->id);

        $this->assertFalse($category->fresh()->hasImage());
        $this->assertNull($category->fresh()->image_size_bytes);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_a_category_takes_its_picture_with_it(): void
    {
        $category = $this->category('Frozen');
        app(ImageService::class)->storeForCategory($category, UploadedFile::fake()->image('peas.jpg', 500, 500));

        $path = $category->fresh()->image_path;

        Livewire::test(CategoryIndex::class)->call('delete', $category->id);

        $this->assertNull(Category::find($category->id));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_category_pictures_count_towards_the_shops_image_allowance(): void
    {
        $images = app(ImageService::class);

        $this->assertSame(0.0, $images->storageUsedMb());

        $images->storeForCategory($this->category('Drinks'), UploadedFile::fake()->image('cola.jpg', 900, 900));

        $this->assertGreaterThan(0, $images->storageUsedMb());
    }

    public function test_a_picture_bigger_than_the_plan_allows_is_refused(): void
    {
        // A plan with almost no room at all.
        $tiny = Package::factory()->allowing(['products' => 50, 'storage_mb' => 0])->create();
        app(SubscribeToPackage::class)->handle($this->store, $tiny);
        Entitlements::forget();

        Livewire::test(CategoryIndex::class)
            ->set('name', 'Household')
            ->set('photo', UploadedFile::fake()->image('mop.jpg', 900, 900))
            ->call('save')
            ->assertHasErrors('photo');

        $this->assertFalse(Category::where('name', 'Household')->firstOrFail()->hasImage());
    }

    public function test_only_a_picture_is_accepted(): void
    {
        Livewire::test(CategoryIndex::class)
            ->set('name', 'Tinned goods')
            ->set('photo', UploadedFile::fake()->create('prices.pdf', 40, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('photo');
    }

    /*
     * ----------------------------------------------------- on the shop front
     */

    protected function openGroceryShop(): string
    {
        PackageTemplate::create(['package_id' => $this->package->id, 'template' => 'grocery']);
        app(TemplateCatalogue::class)->choose($this->store, 'grocery');

        return 'http://'.Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);
    }

    public function test_the_grocery_front_page_shows_the_category_picture(): void
    {
        $category = $this->category('Fruit and veg');
        app(ImageService::class)->storeForCategory($category, UploadedFile::fake()->image('fruit.jpg', 700, 700));

        $url = $this->openGroceryShop();
        $thumbnail = $category->fresh()->thumbnailUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Fruit and veg')
            ->assertSee($thumbnail, false);
    }

    public function test_a_category_with_no_picture_falls_back_to_its_first_letter(): void
    {
        $this->category('Bakery');

        $url = $this->openGroceryShop();
        Tenancy::forget();

        $response = $this->get($url)->assertOk();

        $response->assertSee('Bakery');
        $response->assertDontSee('categories/', false);
    }

    public function test_one_shop_cannot_see_another_shops_category_picture(): void
    {
        $mine = $this->category('Mine');
        app(ImageService::class)->storeForCategory($mine, UploadedFile::fake()->image('a.jpg', 400, 400));

        $other = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        app(SubscribeToPackage::class)->handle(
            $other,
            Package::factory()->allowing(['products' => 50, 'storage_mb' => 100])->create(),
        );
        Entitlements::forget();

        $theirs = Tenancy::run($other, function () use ($other) {
            $category = Category::create(['tenant_id' => $other->id, 'name' => 'Theirs', 'slug' => 'theirs']);
            app(ImageService::class)->storeForCategory($category, UploadedFile::fake()->image('b.jpg', 400, 400));

            return $category->fresh();
        });

        Entitlements::forget();

        // Their picture is filed under their shop, and their category is not
        // even visible from inside mine.
        $this->assertStringContainsString('shops/'.$other->id.'/', $theirs->image_path);
        $this->assertStringContainsString('shops/'.$this->store->id.'/', $mine->fresh()->image_path);
        $this->assertNull(Category::find($theirs->id));
    }
}
