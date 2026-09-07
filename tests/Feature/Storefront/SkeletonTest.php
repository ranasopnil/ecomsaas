<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The grey outline shown while the next page is on its way.
 *
 * It has to be on every page rather than the few that were remembered, which
 * is why it lives in the shared layout. This test is what stops a new page
 * from quietly going without one.
 */
class SkeletonTest extends TestCase
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

    /** @return array<int, string> */
    protected function shapes(): array
    {
        return ['home', 'browse', 'product', 'basket', 'admin-list', 'admin-form', 'generic'];
    }

    public function test_every_page_of_a_shop_carries_the_outline(): void
    {
        app(TemplateCatalogue::class)->choose($this->store, 'grocery');

        $created = app(ProductService::class)->create([
            'name' => 'Basmati rice', 'regular_price' => '120', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product = $created instanceof Product ? $created : $created->product;

        $url = $this->shopUrl();
        Tenancy::forget();

        foreach (['/', '/browse', '/basket', '/products/'.$product->slug] as $page) {
            $response = $this->get($url.$page)->assertOk();

            $response->assertSee('data-page-body', false);

            foreach ($this->shapes() as $shape) {
                $response->assertSee('data-skeleton="'.$shape.'"', false);
            }
        }
    }

    public function test_the_plain_shop_front_has_it_too(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('data-page-body', false)
            ->assertSee('data-skeleton="home"', false);
    }

    public function test_the_shopkeeper_s_own_screens_have_it(): void
    {
        Tenancy::set($this->store);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        $url = $this->shopUrl();

        $this->get($url.'/admin')
            ->assertOk()
            ->assertSee('data-page-body', false)
            ->assertSee('data-skeleton="admin-list"', false)
            ->assertSee('data-skeleton="admin-form"', false);
    }

    public function test_the_outline_is_never_read_out_as_content(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        // Inside <template>, so nothing in it is drawn, focusable or read
        // aloud until a script puts it on the page.
        $body = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('<template data-skeleton="home">', $body);
    }
}
