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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shop front finding the customer for itself.
 *
 * A grocery shop is useless until it knows where the shopper is, so the page
 * asks the browser as it opens rather than waiting to be told. The answer
 * comes back as two numbers; the customer is shown a place name. Anyone who
 * would rather shop for somewhere else can always say so.
 */
class FindingTheCustomerTest extends TestCase
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

    protected function sellSomething(string $name): Product
    {
        $created = app(ProductService::class)->create([
            'name' => $name, 'regular_price' => '120', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        return $created instanceof Product ? $created : $created->product;
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    /*
     * ------------------------------------------------- asking on arrival
     */

    public function test_the_shop_front_asks_the_browser_where_the_customer_is(): void
    {
        $this->sellSomething('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Search location here')
            // The hero is told to go and ask, because nothing is known yet.
            ->assertSee('auto\u0022:true', false);
    }

    public function test_it_does_not_ask_again_once_the_customer_is_known(): void
    {
        $this->sellSomething('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->withSession(['shopper.location' => [
            'latitude' => 23.7925, 'longitude' => 90.4078, 'label' => 'Gulshan, Dhaka',
        ]])
            ->get($url)
            ->assertOk()
            ->assertSee('auto\u0022:false', false)
            ->assertSee('Gulshan, Dhaka');
    }

    public function test_a_known_customer_can_still_change_where_they_are(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->withSession(['shopper.location' => [
            'latitude' => 23.7925, 'longitude' => 90.4078, 'label' => 'Gulshan, Dhaka',
        ]])
            ->get($url)
            ->assertOk()
            ->assertSee('Search another location')
            ->assertSee('Show me everything instead');
    }

    /*
     * ------------------------------------------- turning numbers into a place
     */

    public function test_a_position_with_no_name_is_given_one_from_the_map(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Mirpur, Dhaka, Bangladesh',
            ]),
        ]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url)
            ->post($url.'/where-i-am', ['latitude' => 23.8223, 'longitude' => 90.3654])
            ->assertRedirect($url);

        $this->assertSame('Mirpur, Dhaka, Bangladesh', session('shopper.location.label'));
    }

    public function test_a_map_service_that_is_down_still_lets_the_customer_shop(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url)
            ->post($url.'/where-i-am', ['latitude' => 23.8223, 'longitude' => 90.3654])
            ->assertRedirect($url);

        $this->assertNull(session('shopper.location.label'));
        $this->assertSame(23.8223, session('shopper.location.latitude'));
    }

    public function test_a_name_the_customer_picked_is_never_looked_up_again(): void
    {
        Http::fake();

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url)->post($url.'/where-i-am', [
            'latitude' => 23.7925, 'longitude' => 90.4078, 'label' => 'Gulshan, Dhaka',
        ]);

        Http::assertNothingSent();
        $this->assertSame('Gulshan, Dhaka', session('shopper.location.label'));
    }

    /*
     * ------------------------------------------------------- the shop's figures
     */

    public function test_the_figures_on_the_front_are_the_shop_s_own_real_ones(): void
    {
        $this->sellSomething('Basmati rice');
        $this->sellSomething('Fresh milk');
        Category::create(['tenant_id' => $this->store->id, 'name' => 'Rice', 'slug' => 'rice']);
        Category::create(['tenant_id' => $this->store->id, 'name' => 'Dairy', 'slug' => 'dairy']);

        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 10,
        ]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Items on sale')
            ->assertSee('Categories to browse')
            ->assertSee('Delivery area')
            // Never a padded number: two items on sale means two.
            ->assertDontSee('10,000+');
    }

    public function test_a_shop_with_no_areas_drawn_says_it_delivers_everywhere(): void
    {
        $this->sellSomething('Basmati rice');
        Category::create(['tenant_id' => $this->store->id, 'name' => 'Rice', 'slug' => 'rice']);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)->assertOk()->assertSee('Everywhere');
    }

    /*
     * ------------------------------------------------------- looking for a thing
     */

    public function test_the_search_box_actually_narrows_the_shop(): void
    {
        $this->sellSomething('Basmati rice');
        $this->sellSomething('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'?q=milk')
            ->assertOk()
            ->assertSee('Fresh milk')
            ->assertDontSee('Basmati rice');
    }

    public function test_a_search_that_finds_nothing_says_so_plainly(): void
    {
        $this->sellSomething('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'?q=chocolate')
            ->assertOk()
            ->assertSee('Nothing matched that');
    }
}
