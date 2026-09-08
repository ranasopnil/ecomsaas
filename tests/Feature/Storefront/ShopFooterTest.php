<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\StorefrontFooter;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bottom of every page of a shop.
 *
 * A shopkeeper types their address, where else to find them, and the pages a
 * customer wants to read before buying. Anything they leave blank is left out
 * rather than shown empty, and a page they have not written does not exist.
 */
class ShopFooterTest extends TestCase
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

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    protected function fillIn(array $with = []): StorefrontFooter
    {
        return StorefrontFooter::create(array_merge([
            'tenant_id' => $this->store->id,
            'about' => 'Groceries delivered across Dhaka since 2019.',
            'address' => 'House 12, Road 5, Dhanmondi, Dhaka 1205',
            'phone' => '+880 1712 345678',
            'contact_email' => 'hello@dhakashop.test',
            'opening_hours' => 'Saturday to Thursday, 9am to 9pm',
            'facebook_url' => 'https://facebook.com/dhakashop',
            'whatsapp_number' => '+880 1712 345678',
            'refund_policy' => "Bring it back within seven days.\n\nWe pay the money back the way it came.",
            'copyright' => 'A family shop.',
        ], $with));
    }

    public function test_the_footer_shows_what_the_shopkeeper_typed(): void
    {
        $this->fillIn();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Groceries delivered across Dhaka since 2019.')
            ->assertSee('House 12, Road 5, Dhanmondi, Dhaka 1205')
            ->assertSee('+880 1712 345678')
            ->assertSee('hello@dhakashop.test')
            ->assertSee('Saturday to Thursday, 9am to 9pm')
            ->assertSee('https://facebook.com/dhakashop', false)
            ->assertSee('https://wa.me/8801712345678', false)
            ->assertSee('Refund policy')
            ->assertSee('A family shop.');
    }

    public function test_a_shop_that_has_typed_nothing_still_has_a_footer(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee($this->store->name)
            ->assertSee('Everything we sell')
            // The address the shop signed up with stands in until they type
            // one of their own. Nothing else is invented.
            ->assertSee($this->store->email)
            ->assertDontSee('Good to know');
    }

    public function test_a_written_page_can_be_read(): void
    {
        $this->fillIn();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/pages/refund-policy')
            ->assertOk()
            ->assertSee('Refund policy')
            ->assertSee('Bring it back within seven days.')
            ->assertSee('We pay the money back the way it came.');
    }

    public function test_a_page_the_shopkeeper_has_not_written_is_not_there(): void
    {
        $this->fillIn();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/pages/privacy-policy')->assertNotFound();
        $this->get($url.'/pages/anything-else')->assertNotFound();
    }

    public function test_a_link_typed_without_the_https_still_works(): void
    {
        $footer = $this->fillIn(['instagram_url' => 'instagram.com/dhakashop']);

        // Stored as typed; shown as a link that opens.
        $this->assertSame('https://instagram.com/dhakashop', StorefrontFooter::tidyUrl($footer->instagram_url));
    }

    public function test_a_link_that_is_not_an_ordinary_web_address_is_never_put_on_the_shop(): void
    {
        $this->assertNull(StorefrontFooter::tidyUrl('javascript:alert(1)'));
        $this->assertNull(StorefrontFooter::tidyUrl('data:text/html,<script>'));
        $this->assertNull(StorefrontFooter::tidyUrl('   '));
    }

    public function test_what_a_shopkeeper_types_is_shown_as_words_and_never_as_markup(): void
    {
        $this->fillIn(['about' => 'We are <script>alert(1)</script> honest.']);

        $url = $this->shopUrl();
        Tenancy::forget();

        $page = $this->get($url)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;', $page);
    }

    public function test_one_shop_never_shows_another_shops_footer(): void
    {
        $this->fillIn(['about' => 'The first shop.']);

        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        $package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($other, $package);
        PackageTemplate::create(['package_id' => $package->id, 'template' => 'grocery']);

        Tenancy::run($other, function () use ($other) {
            StorefrontFooter::create(['tenant_id' => $other->id, 'about' => 'The second shop.']);
            app(TemplateCatalogue::class)->choose($other, 'grocery');
        });

        $otherUrl = 'http://'.Tenancy::run($other, fn () => Domain::factory()->create([
            'tenant_id' => $other->id,
        ])->hostname);

        Tenancy::forget();

        $this->get($otherUrl)
            ->assertOk()
            ->assertSee('The second shop.')
            ->assertDontSee('The first shop.');
    }
}
