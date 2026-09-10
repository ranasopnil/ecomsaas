<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Orders\PlaceOrder;
use App\Services\Storefront\Basket;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The electronics shop front.
 *
 * A gadget shop is shopped by make, by what is reduced and by what sells, so
 * this look adds those three rows. Every one of them is counted from the
 * shop's own records: these prove that a row with nothing behind it is left
 * out rather than shown empty, that "top selling" means what actually sold,
 * and that the promises across the top are read from the shop's own settings
 * and never invented.
 */
class ElectronicsTemplateTest extends TestCase
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
        PackageTemplate::create(['package_id' => $this->package->id, 'template' => 'electronics']);

        Entitlements::forget();
        Tenancy::set($this->store);

        app(TemplateCatalogue::class)->choose($this->store, 'electronics');
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function brand(string $name): Brand
    {
        return Brand::create([
            'tenant_id' => $this->store->id, 'name' => $name,
            'slug' => str($name)->slug()->value(), 'is_active' => true,
        ]);
    }

    protected function category(string $name): Category
    {
        return Category::create([
            'tenant_id' => $this->store->id, 'name' => $name,
            'slug' => str($name)->slug()->value(), 'is_active' => true,
        ]);
    }

    /**
     * Something on the shelves. `discount_price` below `regular_price` is
     * what makes it a genuine reduction.
     *
     * @param  array<string, mixed>  $with
     */
    protected function sell(string $name, array $with = []): Product
    {
        $created = app(ProductService::class)->create(array_merge([
            'name' => $name, 'regular_price' => '50000', 'stock' => 5,
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

    /**
     * One order for this product, so it has really sold.
     */
    protected function buy(Product $product): Order
    {
        return Tenancy::run($this->store, function () use ($product) {
            PaymentMethod::firstOrCreate(
                ['tenant_id' => $this->store->id, 'gateway' => 'cod'],
                ['is_enabled' => true, 'position' => 0],
            );

            $area = DeliveryArea::firstOrCreate(
                ['tenant_id' => $this->store->id, 'name' => 'Mirpur'],
                ['latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 40, 'delivery_charge_minor' => 6000],
            );

            request()->setLaravelSession(app('session.store'));

            $basket = app(Basket::class);
            $basket->clear();
            $basket->add($product->fresh()->variants->first(), 1);

            return app(PlaceOrder::class)->place(
                $basket,
                ['name' => 'Rahim Uddin', 'phone' => '01711223344', 'address' => 'House 4, Mirpur'],
                'cod',
                $area,
            );
        });
    }

    /*
     * ------------------------------------------------- the look itself
     */

    public function test_the_electronics_look_is_a_template_a_shop_can_be_given(): void
    {
        $catalogue = app(TemplateCatalogue::class);

        $this->assertNotNull($catalogue->find('electronics'));
        $this->assertSame('Electronics', $catalogue->find('electronics')['name']);
        $this->assertTrue($catalogue->mayUse($this->store, 'electronics'));
        $this->assertSame('electronics', $catalogue->activeFor($this->store));

        // It is not in every plan, so it is something staff give out.
        $this->assertFalse($catalogue->isAlwaysIncluded('electronics'));
    }

    public function test_a_shop_that_loses_the_template_falls_back_rather_than_breaking(): void
    {
        PackageTemplate::where('package_id', $this->package->id)->delete();

        $this->assertSame('classic', app(TemplateCatalogue::class)->activeFor($this->store->fresh()));
    }

    public function test_the_front_page_shows_the_make_the_name_and_the_price(): void
    {
        $apple = $this->brand('Apple');
        $this->sell('iPhone 17 Pro', ['brand_id' => $apple->id]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('New arrivals')
            ->assertSee('iPhone 17 Pro')
            ->assertSee('Apple')
            ->assertSee('৳50,000.00')
            ->assertSee('In stock');
    }

    /*
     * ------------------------------------------------- shopping by make
     */

    public function test_the_brand_strip_only_shows_makes_with_something_behind_them(): void
    {
        $apple = $this->brand('Apple');
        $this->brand('Empty Make');
        $this->sell('MacBook Air', ['brand_id' => $apple->id]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Shop by brand')
            ->assertSee('Apple')
            // A make with nothing on sale would lead to an empty page.
            ->assertDontSee('Empty Make');
    }

    public function test_a_make_can_be_browsed_on_its_own(): void
    {
        $apple = $this->brand('Apple');
        $samsung = $this->brand('Samsung');

        $this->sell('MacBook Air', ['brand_id' => $apple->id]);
        $this->sell('Galaxy S25', ['brand_id' => $samsung->id]);

        $url = $this->shopUrl();
        Tenancy::forget();

        // The search box's example is the newest thing in the shop, so the
        // words alone prove nothing. The links do.
        $this->get($url.'/browse?brand=apple')
            ->assertOk()
            ->assertSee('/products/macbook-air')
            ->assertDontSee('/products/galaxy-s25');
    }

    /*
     * ------------------------------------------------- what is reduced
     */

    public function test_a_reduction_shows_the_old_price_the_percentage_and_the_saving(): void
    {
        $this->sell('iPad Air', ['regular_price' => '80000', 'discount_price' => '60000']);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Reduced right now')
            ->assertSee('৳60,000.00')
            ->assertSee('৳80,000.00')
            ->assertSee('&minus;25%', false)
            ->assertSee('Save ৳20,000.00');
    }

    public function test_a_shop_with_nothing_reduced_has_no_reduced_row_at_all(): void
    {
        $this->sell('iPad Air');

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertDontSee('Reduced right now')
            ->assertDontSee('What is reduced');
    }

    /*
     * ------------------------------------------------- what actually sells
     */

    public function test_top_selling_is_counted_from_orders_and_is_absent_until_something_sells(): void
    {
        $watch = $this->sell('Apple Watch');
        $this->sell('AirPods Pro');

        $url = $this->shopUrl();
        Tenancy::forget();

        // Nothing has sold, so there is no best-sellers row to show.
        $this->get($url)->assertOk()->assertDontSee('Top selling');

        Tenancy::set($this->store);
        $this->buy($watch);
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Top selling')
            ->assertSee('Counted from what this shop has actually sold');
    }

    /*
     * ------------------------------------------------- the promises strip
     */

    public function test_the_promises_are_read_from_the_shop_and_never_invented(): void
    {
        $this->sell('AirPods Pro');

        Tenancy::run($this->store, fn () => PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod', 'is_enabled' => true, 'position' => 0,
        ]));

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('Pay on delivery')
            ->assertSee('Delivered anywhere')
            // The shop this look is modelled on promises these. We cannot
            // know either, so we never say them.
            ->assertDontSee('0% EMI')
            ->assertDontSee('100% Secure')
            ->assertDontSee('Authorized Reseller');
    }

    /*
     * ------------------------------------------------- its unique name
     */

    public function test_every_template_carries_its_own_unchanging_name(): void
    {
        $keys = app(TemplateCatalogue::class)->all()->keys();

        $this->assertSame($keys->unique()->count(), $keys->count(), 'Two templates share a name.');
        $this->assertContains('electronics', $keys->all());
    }
}
