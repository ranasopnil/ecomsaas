<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\TemplateIndex;
use App\Livewire\Super\MapAccess;
use App\Livewire\Super\TemplateMatrix;
use App\Models\Admin;
use App\Models\DeliveryArea;
use App\Models\Domain;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\MapProviders;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Choosing how a shop looks, and who is allowed which look.
 *
 * A shop sees every template. Its plan decides which it may actually use, and
 * losing a plan must never leave a shop with no shop front at all.
 */
class ShopTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $this->package = Package::factory()->allowing(['products' => 50])->create();
        app(SubscribeToPackage::class)->handle($this->store, $this->package);

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function asShopkeeper(): void
    {
        Tenancy::set($this->store);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function grantGrocery(): void
    {
        PackageTemplate::create(['package_id' => $this->package->id, 'template' => 'grocery']);
    }

    protected function catalogue(): TemplateCatalogue
    {
        return app(TemplateCatalogue::class);
    }

    /*
     * ------------------------------------------------- what a shop may use
     */

    public function test_the_plain_template_comes_with_every_plan(): void
    {
        $this->assertTrue($this->catalogue()->mayUse($this->store, 'classic'));
        $this->assertSame('classic', $this->catalogue()->activeFor($this->store));
    }

    public function test_a_shop_sees_every_template_even_the_ones_it_cannot_use(): void
    {
        $shown = $this->catalogue()->forShop($this->store);

        $this->assertTrue($shown->has('grocery'));
        $this->assertFalse($shown->get('grocery')['included']);
        $this->assertTrue($shown->get('classic')['included']);
    }

    public function test_a_shop_cannot_choose_a_template_its_plan_leaves_out(): void
    {
        $this->asShopkeeper();

        Livewire::test(TemplateIndex::class)
            ->call('choose', 'grocery')
            ->assertDispatched('toast');

        $this->assertNull($this->store->fresh()->template);
        $this->assertSame('classic', $this->catalogue()->activeFor($this->store->fresh()));
    }

    public function test_a_shop_can_choose_a_template_its_plan_includes(): void
    {
        $this->grantGrocery();
        $this->asShopkeeper();

        Livewire::test(TemplateIndex::class)->call('choose', 'grocery');

        $this->assertSame('grocery', $this->store->fresh()->template);
        $this->assertSame('grocery', $this->catalogue()->activeFor($this->store->fresh()));
    }

    public function test_losing_the_plan_falls_back_rather_than_leaving_no_shop_front(): void
    {
        $this->grantGrocery();
        $this->catalogue()->choose($this->store, 'grocery');

        $this->assertSame('grocery', $this->catalogue()->activeFor($this->store->fresh()));

        // The plan stops including it, but the shop's choice is left alone.
        PackageTemplate::where('template', 'grocery')->delete();

        $shop = $this->store->fresh();
        $this->assertSame('classic', $this->catalogue()->activeFor($shop));
        $this->assertSame('grocery', $shop->template, 'The shop’s choice should be remembered for when the plan comes back.');
    }

    /*
     * ------------------------------------------------------- staff control
     */

    public function test_staff_decide_which_plan_gets_which_template(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(TemplateMatrix::class)->call('toggle', 'grocery', $this->package->id);

        $this->assertTrue($this->catalogue()->packageIncludes($this->package->fresh(), 'grocery'));

        Livewire::test(TemplateMatrix::class)->call('toggle', 'grocery', $this->package->id);

        $this->assertFalse($this->catalogue()->packageIncludes($this->package->fresh(), 'grocery'));
    }

    public function test_a_template_every_plan_includes_cannot_be_taken_away(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(TemplateMatrix::class)->call('toggle', 'classic', $this->package->id);

        $this->assertTrue($this->catalogue()->packageIncludes($this->package->fresh(), 'classic'));
        $this->assertSame(0, PackageTemplate::where('template', 'classic')->count());
    }

    /*
     * ------------------------------------------------------------ the maps
     */

    public function test_a_shop_gets_the_free_map_until_staff_say_otherwise(): void
    {
        $this->assertSame('osm', app(MapProviders::class)->forShop($this->store));
    }

    public function test_google_cannot_be_handed_out_without_a_key(): void
    {
        config()->set('services.google_maps.key', null);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(MapAccess::class)->call('assign', $this->store->id, 'google');

        $this->assertNull($this->store->fresh()->map_provider);
        $this->assertSame('osm', app(MapProviders::class)->forShop($this->store->fresh()));
    }

    public function test_staff_can_hand_a_shop_google_once_a_key_exists(): void
    {
        config()->set('services.google_maps.key', 'a-key');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(MapAccess::class)->call('assign', $this->store->id, 'google');

        $this->assertSame('google', app(MapProviders::class)->forShop($this->store->fresh()));
    }

    /*
     * ------------------------------------------------------- the shop front
     */

    protected function sellSomething(string $name, array $attributes = []): Product
    {
        $created = app(ProductService::class)->create([
            'name' => $name, 'regular_price' => '120', 'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $product = $created instanceof Product ? $created : $created->product;

        if ($attributes !== []) {
            $product->forceFill($attributes)->save();
        }

        return $product->fresh();
    }

    protected function shopUrl(): string
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        return 'http://'.$hostname;
    }

    public function test_the_shop_front_uses_the_template_the_shopkeeper_chose(): void
    {
        $this->grantGrocery();
        $this->catalogue()->choose($this->store, 'grocery');
        $this->sellSomething('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Delivered', false)
            ->assertSee('Discover everything you need near you')
            ->assertSee('Basmati rice')
            ->assertSee('Set where you are');
    }

    public function test_a_shop_on_the_plain_template_gets_the_plain_page(): void
    {
        $this->sellSomething('Basmati rice');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Basmati rice')
            ->assertDontSee('daily needs');
    }

    public function test_a_customer_only_sees_what_reaches_them(): void
    {
        $this->grantGrocery();
        $this->catalogue()->choose($this->store, 'grocery');

        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 10,
        ]);

        $this->sellSomething('Fresh milk');
        $this->sellSomething('Dried lentils', ['availability' => Product::AVAILABLE_ANYWHERE]);

        $url = $this->shopUrl();
        Tenancy::forget();

        // Someone in another city: only the posted item reaches them.
        $this->withSession(['shopper.location' => [
            'latitude' => 22.3569, 'longitude' => 91.7832, 'label' => 'Chittagong',
        ]])
            ->get($url)
            ->assertOk()
            ->assertSee('Dried lentils')
            ->assertDontSee('Fresh milk')
            ->assertSee('not delivered to');
    }

    public function test_a_customer_who_has_not_said_where_they_are_sees_the_whole_shop(): void
    {
        DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => 'Dhaka city', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_km' => 1,
        ]);

        $this->sellSomething('Fresh milk');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)->assertOk()->assertSee('Fresh milk');
    }

    public function test_a_customer_can_say_where_they_are_and_change_their_mind(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url)
            ->post($url.'/where-i-am', [
                'latitude' => 23.7925, 'longitude' => 90.4078, 'label' => 'Gulshan, Dhaka',
            ])
            ->assertRedirect($url);

        $this->assertSame('Gulshan, Dhaka', session('shopper.location.label'));

        $this->from($url)->post($url.'/where-i-am/forget')->assertRedirect($url);

        $this->assertNull(session('shopper.location'));
    }

    public function test_a_position_that_is_not_on_the_earth_is_refused(): void
    {
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->from($url)
            ->post($url.'/where-i-am', ['latitude' => 999, 'longitude' => 90.4078])
            ->assertSessionHasErrors('latitude');
    }
}
