<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A shop opened on a phone.
 *
 * Every look has to feel like an app rather than a page that happens to be
 * narrow: something fixed along the bottom to walk about with, the shop's own
 * icon and colour so it can be kept on a home screen, and the thing you came
 * to press within reach of a thumb.
 *
 * These prove the parts that are easy to break by accident — that the strip
 * is on every page of every look, that it never offers a place that is not
 * there, and that the icon and the note a phone reads are the shop's own.
 */
class OnAPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'name' => 'Dhaka Fashion', 'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $this->package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($this->store, $this->package);

        foreach (['grocery', 'electronics'] as $look) {
            PackageTemplate::create(['package_id' => $this->package->id, 'template' => $look]);
        }

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();
        Cache::flush();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $with
     */
    protected function sell(string $name, array $with = []): Product
    {
        $created = app(ProductService::class)->create(array_merge([
            'name' => $name, 'regular_price' => '500', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ], $with));

        return $created instanceof Product ? $created : $created->product;
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    protected function wearing(string $look): void
    {
        app(TemplateCatalogue::class)->choose($this->store, $look);
        Cache::flush();
    }

    /*
     * ------------------------------------------------- the strip at the foot
     */

    public function test_every_look_has_the_strip_along_the_bottom(): void
    {
        $this->sell('Cotton shirt');
        $url = $this->shopUrl();

        foreach (['classic', 'grocery', 'electronics'] as $look) {
            Tenancy::set($this->store);
            $this->wearing($look);
            Tenancy::forget();

            $page = $this->get($url)->assertOk();

            $page->assertSee('aria-label="Shop"', false)
                ->assertSee('data-tab="home"', false)
                ->assertSee('data-tab="browse"', false)
                ->assertSee('data-tab="basket"', false);

            // It is for a phone only: a mouse has the top bar and the page.
            $this->assertStringContainsString('md:hidden', $page->getContent());
        }
    }

    public function test_the_strip_is_on_every_page_a_shopper_lands_on(): void
    {
        $product = $this->sell('Cotton shirt');

        Tenancy::set($this->store);
        $this->wearing('electronics');
        $url = $this->shopUrl();
        Tenancy::forget();

        foreach (['/', '/browse', '/products/'.$product->slug, '/basket'] as $path) {
            $this->get($url.$path)
                ->assertOk()
                ->assertSee('aria-label="Shop"', false);
        }
    }

    public function test_offers_are_only_offered_when_the_shop_has_some(): void
    {
        $this->sell('Cotton shirt');

        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        // Nothing reduced, so the strip does not lead anywhere empty.
        $this->get($url)->assertOk()->assertDontSee('data-tab="offers"', false);

        Tenancy::set($this->store);
        $this->sell('Silk scarf', ['regular_price' => '900', 'discount_price' => '600']);
        Cache::flush();
        Tenancy::forget();

        $this->get($url)->assertOk()->assertSee('data-tab="offers"', false);
    }

    /*
     * ------------------------------------------------- keeping it on a phone
     */

    public function test_a_phone_is_told_how_to_keep_the_shop_on_a_home_screen(): void
    {
        $this->sell('Cotton shirt');

        Tenancy::set($this->store);
        $this->wearing('electronics');
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            // The colour of the look the shop is actually wearing.
            ->assertSee('<meta name="theme-color" content="#f26e21">', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_the_note_a_phone_reads_is_this_shops_own(): void
    {
        Tenancy::set($this->store);
        $this->wearing('electronics');
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/manifest.webmanifest')
            ->assertOk()
            ->assertJson([
                'name' => 'Dhaka Fashion',
                'display' => 'standalone',
                'theme_color' => '#f26e21',
            ]);
    }

    public function test_the_icon_is_the_shops_own_letter_in_its_own_colour(): void
    {
        Tenancy::set($this->store);
        $this->wearing('grocery');
        $url = $this->shopUrl();
        Tenancy::forget();

        $svg = $this->get($url.'/icon.svg')->assertOk();

        $svg->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('#16a34a', $svg->getContent(), 'The grocery green.');
        $this->assertStringContainsString('>D<', $svg->getContent(), 'Dhaka Fashion.');

        $this->get($url.'/icon.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_asking_for_the_icon_is_not_counted_as_somebody_visiting(): void
    {
        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/icon.svg')->assertOk();
        $this->get($url.'/manifest.webmanifest')->assertOk();

        Tenancy::set($this->store);
        $this->assertDatabaseCount('visit_days', 0);
    }

    /*
     * ------------------------------------------- the thing you came to press
     */

    public function test_a_product_keeps_buy_now_within_reach_of_a_thumb(): void
    {
        $product = $this->sell('Cotton shirt');

        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url.'/products/'.$product->slug)->assertOk();

        // The page leaves room for it, and the bar is there.
        $this->assertStringContainsString('has-bar', $page->getContent());
        $this->assertStringContainsString('app-bar', $page->getContent());
        $page->assertSee('Buy now');
    }

    public function test_a_sold_out_product_is_given_no_buy_bar_and_no_room_for_one(): void
    {
        $product = $this->sell('Cotton shirt', ['stock' => 0]);

        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url.'/products/'.$product->slug)->assertOk();

        $this->assertStringNotContainsString('has-bar', $page->getContent());
        $this->assertStringNotContainsString('app-bar', $page->getContent());
    }

    public function test_an_empty_basket_gets_no_checkout_bar(): void
    {
        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url.'/basket')->assertOk();

        $this->assertStringNotContainsString('app-bar', $page->getContent());
        $page->assertSee('Nothing in here yet');
    }

    /*
     * ------------------------------------------------- the aisles on a phone
     */

    public function test_browsing_puts_the_aisles_on_one_line_a_thumb_can_push(): void
    {
        $this->sell('Cotton shirt');

        Tenancy::set($this->store);
        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url.'/browse')->assertOk();

        // The wall of links down the side is for a wide screen only.
        $this->assertStringContainsString('swipe-row', $page->getContent());
        $this->assertStringContainsString('hidden lg:mb-0 lg:block', $page->getContent());
    }
}
