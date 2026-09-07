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
            ->assertSee('Search fresh essentials')
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
     * ------------------------------------------------- changing only the shelf
     */

    public function test_the_page_marks_out_the_parts_that_change_with_the_category(): void
    {
        $this->category('Dairy');
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse')
            ->assertOk()
            // The two regions the script swaps, and the outline it shows
            // while it waits for them.
            ->assertSee('data-swap="side"', false)
            ->assertSee('data-swap="main"', false)
            ->assertSee('data-skeleton="results"', false)
            // Category links say they only change the shelf.
            ->assertSee('data-swap-link', false);
    }

    public function test_a_category_link_is_still_a_plain_link(): void
    {
        $dairy = $this->category('Dairy');
        $this->sell('Fresh milk', ['category_ids' => [$dairy->id]]);
        $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        // Opened directly, with no script at all, it is a whole working page.
        $this->get($url.'/browse?category=dairy')
            ->assertOk()
            ->assertSee('<!DOCTYPE html>', false)
            ->assertSee('/products/fresh-milk')
            ->assertDontSee('/products/basmati-rice');
    }

    /*
     * ------------------------------------------- what the price is the price of
     */

    public function test_a_price_says_what_it_is_the_price_of(): void
    {
        $this->sell('Basmati rice', ['unit' => 'per kg']);

        $url = $this->shopUrl();
        Tenancy::forget();

        // On the shelf,
        $this->get($url.'/browse')->assertOk()->assertSee('per kg');

        // on the product itself,
        $this->get($url.'/products/basmati-rice')->assertOk()->assertSee('per kg');

        // and while typing.
        $this->getJson($url.'/search/suggestions?q=rice')
            ->assertOk()
            ->assertJsonPath('results.0.unit', 'per kg');
    }

    public function test_the_basket_says_it_too(): void
    {
        $rice = $this->sell('Basmati rice', ['unit' => 'per kg']);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id]);

        $this->get($url.'/basket')->assertOk()->assertSee('per kg');
    }

    public function test_a_product_with_nothing_to_say_falls_back_to_each(): void
    {
        $rice = $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id]);

        $this->get($url.'/basket')->assertOk()->assertSee('each');
    }

    /*
     * --------------------------------------------------- suggesting as you type
     */

    public function test_typing_brings_up_what_matches(): void
    {
        $this->sell('Basmati rice');
        $this->sell('Brown rice');
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $answer = $this->getJson($url.'/search/suggestions?q=rice')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'results')
            ->json();

        $names = array_column($answer['results'], 'name');

        sort($names);
        $this->assertSame(['Basmati rice', 'Brown rice'], $names);

        // Enough to show a row: something to tap, and what it costs.
        $this->assertStringContainsString('/products/', $answer['results'][0]['url']);
        $this->assertSame('৳120.00', $answer['results'][0]['price']);
    }

    public function test_one_letter_is_not_worth_asking_about(): void
    {
        $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->getJson($url.'/search/suggestions?q=r')
            ->assertOk()
            ->assertExactJson(['results' => [], 'total' => 0]);
    }

    public function test_it_never_suggests_something_that_cannot_reach_the_customer(): void
    {
        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 10,
        ]);

        $this->sell('Basmati rice');
        $this->sell('Brown rice', ['availability' => Product::AVAILABLE_ANYWHERE]);

        $url = $this->shopUrl();
        Tenancy::forget();

        // Somebody in Chittagong: only the one that goes anywhere.
        $answer = $this->withSession(['shopper.location' => [
            'latitude' => 22.3569, 'longitude' => 91.7832, 'label' => 'Chittagong',
        ]])
            ->getJson($url.'/search/suggestions?q=rice')
            ->assertOk()
            ->json();

        $this->assertSame(['Brown rice'], array_column($answer['results'], 'name'));
        $this->assertSame(1, $answer['total']);
    }

    public function test_the_count_is_of_everything_that_matches_not_of_what_is_shown(): void
    {
        foreach (range(1, 9) as $number) {
            $this->sell("Rice number {$number}");
        }

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->getJson($url.'/search/suggestions?q=rice')
            ->assertOk()
            ->assertJsonPath('total', 9)
            ->assertJsonCount(7, 'results');
    }

    public function test_the_box_still_searches_with_no_javascript(): void
    {
        $this->sell('Basmati rice');
        $this->sell('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/browse?q=rice')
            ->assertOk()
            ->assertSee('/products/basmati-rice')
            ->assertDontSee('/products/fresh-milk');
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

    public function test_adding_from_the_page_answers_instead_of_reloading_it(): void
    {
        $rice = $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->postJson($url.'/basket/add', ['variant_id' => $rice->variants->first()->id, 'quantity' => 2])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'name' => 'Basmati rice', 'count' => 2, 'checkout' => null]);

        // Nothing was flashed for a banner, because no page is being reloaded.
        $this->assertNull(session('basket.added'));
    }

    public function test_buy_now_puts_it_in_and_goes_straight_to_the_till(): void
    {
        $rice = $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        // Without javascript: an ordinary post that redirects to checkout.
        $this->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id, 'then' => 'checkout'])
            ->assertRedirect($url.'/checkout');

        // With it: the same answer, saying where to go next.
        $this->postJson($url.'/basket/add', ['variant_id' => $rice->variants->first()->id, 'then' => 'checkout'])
            ->assertOk()
            ->assertJsonPath('checkout', $url.'/checkout');
    }

    public function test_a_refusal_comes_back_the_same_quiet_way(): void
    {
        $rice = $this->sell('Basmati rice', ['stock' => 0]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->postJson($url.'/basket/add', ['variant_id' => $rice->variants->first()->id])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'count' => 0]);
    }

    public function test_the_plus_still_works_with_no_javascript_at_all(): void
    {
        $rice = $this->sell('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        // An ordinary form post: it redirects back and flashes a banner.
        $this->from($url.'/browse')
            ->post($url.'/basket/add', ['variant_id' => $rice->variants->first()->id])
            ->assertRedirect($url.'/browse')
            ->assertSessionHas('basket.added', 'Basmati rice');
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
