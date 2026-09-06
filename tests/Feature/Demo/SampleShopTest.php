<?php

namespace Tests\Feature\Demo;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Demo\SampleShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SampleShopTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle(
            $this->store,
            Package::factory()->allowing(['products' => 100, 'storage_mb' => 500])->create()
        );

        Entitlements::forget();
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_it_fills_an_empty_shop_with_something_to_look_at(): void
    {
        $result = app(SampleShop::class)->fill($this->store, withPhotos: false);

        $this->assertGreaterThan(15, $result['products']);

        Tenancy::run($this->store, function () {
            $this->assertGreaterThan(15, Product::count());
            $this->assertGreaterThan(0, Category::count());
            $this->assertGreaterThan(0, InventoryMovement::count());
        });
    }

    public function test_the_stock_history_adds_up_to_what_is_on_the_shelf(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, function () {
            $levels = InventoryLevel::with('variant')->get();

            $this->assertNotEmpty($levels);

            foreach ($levels as $level) {
                $last = InventoryMovement::where('product_variant_id', $level->product_variant_id)
                    ->orderByDesc('id')->first();

                $this->assertNotNull($last, 'Every sample line should have a history.');
                $this->assertSame(
                    $level->available,
                    $last->available_after,
                    'What the history ends at must be what the shelf says.'
                );
            }
        });
    }

    public function test_the_history_is_spread_over_the_last_fortnight(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, function () {
            $days = InventoryMovement::query()
                ->selectRaw('DATE(created_at) AS on_day')
                ->distinct()->pluck('on_day');

            $this->assertGreaterThan(5, $days->count(), 'The chart needs more than one day of movement.');
            $this->assertTrue(
                Carbon::parse($days->min())->greaterThanOrEqualTo(Carbon::today()->subDays(15)),
                'Nothing should be older than a fortnight.'
            );
        });
    }

    public function test_some_things_are_sold_out_and_some_are_running_low(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, function () {
            $this->assertGreaterThan(0, InventoryLevel::where('available', '<=', 0)->count());
            $this->assertGreaterThan(0, InventoryLevel::whereNotNull('low_stock_threshold')->count());
        });
    }

    public function test_every_sample_product_has_a_cost_so_the_money_figures_work(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, function () {
            foreach (Product::with('variants')->get() as $product) {
                foreach ($product->variants as $variant) {
                    $this->assertNotNull($variant->cost_price_minor, "{$product->name} has no cost.");
                    $this->assertGreaterThan(0, $variant->price_minor);
                }
            }
        });
    }

    public function test_removing_the_sample_leaves_the_shopkeepers_own_work_alone(): void
    {
        $mine = Tenancy::run($this->store, fn () => app(ProductService::class)->create([
            'name' => 'My Own Product',
            'regular_price' => '999',
            'stock' => 7,
        ]));

        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, fn () => $this->assertGreaterThan(15, Product::count()));

        app(SampleShop::class)->remove($this->store);

        Tenancy::run($this->store, function () use ($mine) {
            $this->assertSame(1, Product::count());
            $this->assertSame('My Own Product', Product::firstOrFail()->name);
            $this->assertSame(7, $mine->fresh('variants')->defaultVariant()->inventory->available);
        });
    }

    public function test_running_it_twice_does_not_double_the_shop(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, fn () => $count = Product::count());
        $first = Tenancy::run($this->store, fn () => Product::count());

        app(SampleShop::class)->fill($this->store, withPhotos: false);

        Tenancy::run($this->store, fn () => $this->assertSame($first, Product::count()));
    }

    public function test_it_stays_inside_the_shops_own_walls(): void
    {
        app(SampleShop::class)->fill($this->store, withPhotos: false);

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Tenancy::run($other, function () {
            $this->assertSame(0, Product::count());
            $this->assertSame(0, InventoryMovement::count());
        });
    }
}
