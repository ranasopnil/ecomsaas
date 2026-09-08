<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop front laid out the way the shop is arranged.
 *
 * First the newest things that actually reach the customer, then one row per
 * category. A category that holds nothing the customer can be sent is left
 * out; a customer looking for one thing is answered with that one thing and
 * not with rows of everything else.
 */
class HomeShelvesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($this->store, $package);
        PackageTemplate::create(['package_id' => $package->id, 'template' => 'grocery']);

        Entitlements::forget();
        Tenancy::set($this->store);
        app(TemplateCatalogue::class)->choose($this->store, 'grocery');
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function category(string $name, ?Category $under = null): Category
    {
        return Category::create([
            'tenant_id' => $this->store->id, 'name' => $name,
            'slug' => str($name)->slug()->value(), 'parent_id' => $under?->id, 'is_active' => true,
        ]);
    }

    protected function sell(string $name, array $with = []): Product
    {
        $availability = $with['availability'] ?? null;
        unset($with['availability']);

        $created = app(ProductService::class)->create(array_merge([
            'name' => $name, 'regular_price' => '120', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ], $with));

        $product = $created instanceof Product ? $created : $created->product;

        if ($availability !== null) {
            $product->forceFill(['availability' => $availability])->save();
        }

        return $product;
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    /**
     * How many category rows the page is showing.
     */
    protected function rowsOn(string $page): int
    {
        return substr_count($page, '<h2 class="truncate text-lg font-bold');
    }

    public function test_the_front_page_shows_a_row_for_each_category(): void
    {
        $dairy = $this->category('Dairy');
        $spices = $this->category('Spices');

        $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);
        $this->sell('Cumin seed', ['category_ids' => [$spices->id]]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Fresh in the shop')
            ->assertSee('Dairy')
            ->assertSee('Spices')
            ->assertSee('/products/fresh-milk')
            ->assertSee('/products/cumin-seed')
            // Each row leads to the rest of that category.
            ->assertSee('/browse?category=dairy', false)
            ->assertSee('/browse?category=spices', false);
    }

    public function test_a_row_also_holds_what_is_underneath_the_category(): void
    {
        $dairy = $this->category('Dairy');
        $cheese = $this->category('Cheese', $dairy);

        $this->sell('Cheddar', ['category_ids' => [$cheese->id]]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Dairy')
            ->assertSee('/products/cheddar');
    }

    public function test_a_category_with_nothing_in_it_is_left_out(): void
    {
        $dairy = $this->category('Dairy');
        $this->category('Frozen');

        $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url)->assertOk()->getContent();

        // "Frozen" is still one of the circles along the top, but it gets no
        // row of its own: an empty shelf tells a shopper nothing.
        $this->assertSame(1, $this->rowsOn($page));
        $this->assertSame(1, substr_count($page, 'browse?category=frozen'));
        $this->assertSame(2, substr_count($page, 'browse?category=dairy'));
    }

    public function test_a_row_only_holds_what_reaches_the_customer(): void
    {
        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 10,
        ]);

        $dairy = $this->category('Dairy');

        $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);
        $this->sell('Powdered milk', [
            'category_ids' => [$dairy->id], 'availability' => Product::AVAILABLE_ANYWHERE,
        ]);

        $url = $this->shopUrl();
        Tenancy::forget();

        // Someone in another city: only the posted one is on the shelf.
        $this->withSession(['shopper.location' => [
            'latitude' => 22.3569, 'longitude' => 91.7832, 'label' => 'Chittagong',
        ]])
            ->get($url)
            ->assertOk()
            ->assertSee('Available for you')
            ->assertSee('Delivered to Chittagong')
            ->assertSee('/products/powdered-milk')
            ->assertDontSee('/products/fresh-milk');
    }

    public function test_searching_answers_with_the_search_and_not_with_the_rows(): void
    {
        $dairy = $this->category('Dairy');
        $spices = $this->category('Spices');

        $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);
        $this->sell('Cumin seed', ['category_ids' => [$spices->id]]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url.'?q=milk')
            ->assertOk()
            ->assertSee('/products/fresh-milk')
            ->assertDontSee('/products/cumin-seed')
            ->getContent();

        $this->assertSame(0, $this->rowsOn($page));
    }

    public function test_it_says_plainly_how_much_of_the_shop_cannot_reach_them(): void
    {
        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 10,
        ]);

        $this->sell('Fresh milk');
        $this->sell('Yoghurt');
        $this->sell('Powdered milk', ['availability' => Product::AVAILABLE_ANYWHERE]);

        $url = $this->shopUrl();
        Tenancy::forget();

        // Two of the three cannot be sent to Chittagong. Counted, not guessed.
        $this->withSession(['shopper.location' => [
            'latitude' => 22.3569, 'longitude' => 91.7832, 'label' => 'Chittagong',
        ]])
            ->get($url)
            ->assertOk()
            ->assertSee('2 more items are not delivered to');
    }
}
