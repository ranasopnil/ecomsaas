<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
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
 * Walking round the shop and picking things up.
 *
 * The browse page shows what is on sale by category or by search. The basket
 * lives in the session, only ever holds this shop's things, and reads prices
 * fresh each time it is shown.
 */
class BrowsingAndBasketTest extends TestCase
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

    protected function sell(string $name, array $with = []): Product
    {
        $created = app(ProductService::class)->create(array_merge([
            'name' => $name, 'regular_price' => '120', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ], $with));

        $product = $created instanceof Product ? $created : $created->product;

        return $product->fresh(['variants.inventory']);
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    protected function category(string $name, ?Category $under = null): Category
    {
        return Category::create([
            'tenant_id' => $this->store->id, 'name' => $name,
            'slug' => strtolower($name), 'parent_id' => $under?->id, 'is_active' => true,
        ]);
    }

    /*
     * ------------------------------------------------------------- browsing
     */

    public function test_the_browse_page_shows_everything_on_sale_with_the_categories_down_the_side(): void
    {
        $this->category('Spices');
        $this->category('Dairy');
        $this->sell('Basmati rice');
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse')
            ->assertOk()
            ->assertSee('Search fresh essentials now')
            ->assertSee('Shop by categories')
            ->assertSee('Spices')
            ->assertSee('Dairy')
            ->assertSee('Basmati rice')
            ->assertSee('Fresh milk')
            ->assertSee('Everything');
    }

    public function test_opening_a_category_shows_only_what_is_in_it_and_what_is_under_it(): void
    {
        $dairy = $this->category('Dairy');
        $cheese = $this->category('Cheese', $dairy);
        $this->category('Spices');

        $milk = $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);
        $this->sell('Cheddar', ['category_ids' => [$cheese->id]]);
        $this->sell('Cumin');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse?category=dairy')
            ->assertOk()
            ->assertSee('/products/fresh-milk')
            ->assertSee('/products/cheddar')
            ->assertDontSee('/products/cumin');

        $this->get($url.'/browse?category=cheese')
            ->assertOk()
            ->assertSee('/products/cheddar')
            ->assertDontSee('/products/fresh-milk');
    }

    public function test_offers_shows_only_what_is_reduced_and_says_the_real_biggest_saving(): void
    {
        $rice = $this->sell('Basmati rice');
        $this->sell('Fresh milk');

        // 120 down from 160 is 25% off. Not "up to 30%".
        $rice->variants->first()->forceFill(['compare_at_price_minor' => 16000])->save();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse')
            ->assertOk()
            ->assertSee('Save up to 25% today')
            ->assertSee("Today's deals", false);

        // Links, not names: the search box shows a real product name as its
        // example, so a name can appear on a page that does not stock it.
        $this->get($url.'/browse?offers=1')
            ->assertOk()
            ->assertSee('/products/basmati-rice')
            ->assertDontSee('/products/fresh-milk');
    }

    public function test_a_shop_with_nothing_reduced_makes_no_promises_about_savings(): void
    {
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse')
            ->assertOk()
            ->assertDontSee('Save up to')
            ->assertDontSee("Today's deals", false);
    }

    public function test_searching_narrows_the_shop(): void
    {
        $this->sell('Basmati rice');
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse?q=milk')
            ->assertOk()
            ->assertSee('Results for')
            ->assertSee('Fresh milk')
            ->assertSee('1 item')
            ->assertDontSee('/products/basmati-rice');
    }

    /*
     * ------------------------------------------------------------- the basket
     */

    public function test_the_plus_on_a_product_puts_one_in_the_basket(): void
    {
        $rice = $this->sell('Basmati rice');
        $variant = $rice->variants->first();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url.'/browse')
            ->post($url.'/basket/add', ['variant_id' => $variant->id])
            ->assertRedirect($url.'/browse')
            ->assertSessionHas('basket.added', 'Basmati rice');

        $this->get($url.'/basket')
            ->assertOk()
            ->assertSee('Basmati rice')
            ->assertSee('৳120.00');
    }

    public function test_the_basket_adds_up_and_can_be_changed(): void
    {
        $rice = $this->sell('Basmati rice');
        $milk = $this->sell('Fresh milk', ['regular_price' => '80']);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id, 'quantity' => 2]);
        $this->post($url.'/basket/add', ['variant_id' => $milk->variants->first()->id]);

        // 2 × 120 + 80
        $this->get($url.'/basket')->assertOk()->assertSee('৳320.00');

        $this->post($url.'/basket/update', ['variant_id' => $rice->variants->first()->id, 'quantity' => 1]);
        $this->get($url.'/basket')->assertOk()->assertSee('৳200.00');

        $this->post($url.'/basket/remove', ['variant_id' => $milk->variants->first()->id]);
        $this->get($url.'/basket')->assertOk()->assertSee('৳120.00')->assertDontSee('Fresh milk');
    }

    public function test_something_sold_out_cannot_go_in(): void
    {
        $rice = $this->sell('Basmati rice', ['stock' => 0]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url.'/browse')
            ->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id])
            ->assertRedirect($url.'/browse')
            ->assertSessionHas('basket.refused');

        $this->get($url.'/basket')->assertOk()->assertSee('Nothing in here yet');
    }

    public function test_another_shop_s_things_cannot_go_in_this_shop_s_basket(): void
    {
        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD']);
        $package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($other, $package);

        $theirVariantId = Tenancy::run($other, function () {
            Entitlements::forget();

            $created = app(ProductService::class)->create([
                'name' => 'Their rice', 'regular_price' => '10', 'stock' => 5, 'status' => Product::STATUS_ACTIVE,
            ]);

            return ($created instanceof Product ? $created : $created->product)->fresh()->variants->first()->id;
        });

        Entitlements::forget();
        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url.'/browse')
            ->post($url.'/basket/add', ['variant_id' => $theirVariantId])
            ->assertSessionHas('basket.refused');

        $this->get($url.'/basket')->assertOk()->assertDontSee('Their rice');
    }

    public function test_something_withdrawn_from_sale_drops_out_of_the_basket(): void
    {
        $rice = $this->sell('Basmati rice');
        $variant = $rice->variants->first();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $variant->id]);

        Tenancy::set($this->store);
        $rice->forceFill(['status' => Product::STATUS_DRAFT])->save();
        Tenancy::forget();

        $this->get($url.'/basket')->assertOk()->assertSee('Nothing in here yet');
    }

    public function test_the_basket_count_shows_on_every_page(): void
    {
        $rice = $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id, 'quantity' => 3]);

        $this->get($url.'/browse')->assertOk()->assertSee('>3<', false);
        $this->get($url.'/products/'.$rice->slug)->assertOk()->assertSee('>3<', false)->assertSee('Add to basket');
    }
}
