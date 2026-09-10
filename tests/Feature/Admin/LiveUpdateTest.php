<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ProductIndex;
use App\Livewire\Admin\StockIndex;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\InventoryService;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Changing something must update that one thing, and say so, without the
 * page being fetched again.
 */
class LiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_counting_stock_reports_back_and_marks_only_that_line(): void
    {
        $variant = app(ProductService::class)
            ->create(['name' => 'Mug', 'regular_price' => '250', 'stock' => 10])
            ->variants->first();

        Livewire::test(StockIndex::class)
            ->set("counted.{$variant->id}", '7')
            ->call('saveCount', $variant->id)
            ->assertSet('justCounted', $variant->id)
            ->assertDispatched('toast');

        $this->assertSame(7, app(InventoryService::class)->levelFor($variant)->fresh()->available);
    }

    public function test_a_missing_count_is_refused_without_touching_the_stock(): void
    {
        $variant = app(ProductService::class)
            ->create(['name' => 'Mug', 'regular_price' => '250', 'stock' => 10])
            ->variants->first();

        Livewire::test(StockIndex::class)
            ->call('saveCount', $variant->id)
            ->assertSet('justCounted', null)
            ->assertDispatched('toast');

        $this->assertSame(10, app(InventoryService::class)->levelFor($variant)->fresh()->available);
    }

    public function test_putting_a_product_away_marks_that_row_only(): void
    {
        $service = app(ProductService::class);
        $kept = $service->create(['name' => 'Kept', 'regular_price' => '100', 'status' => Product::STATUS_ACTIVE]);
        $away = $service->create(['name' => 'Away', 'regular_price' => '100', 'status' => Product::STATUS_ACTIVE]);

        Livewire::test(ProductIndex::class)
            ->call('archive', $away->id)
            ->assertSet('justChanged', $away->id)
            ->assertDispatched('toast');

        $this->assertSame(Product::STATUS_ACTIVE, $kept->fresh()->status);
        $this->assertSame(Product::STATUS_ARCHIVED, $away->fresh()->status);
    }

    public function test_the_dashboard_figures_are_in_the_page_before_any_script_runs(): void
    {
        app(ProductService::class)->create([
            'name' => 'Counted', 'regular_price' => '1000', 'cost_price' => '600', 'stock' => 10,
        ]);

        // The chart and the little lines are drawn by the server, so every
        // figure is on the page with or without JavaScript.
        Livewire::test(Dashboard::class)
            ->assertSee('Total sales')
            ->assertSee('Products')
            ->assertSee('Sales overview')
            ->assertSee('Getting started');
    }

    public function test_the_sales_chart_can_be_stretched_without_leaving_the_page(): void
    {
        app(ProductService::class)->create(['name' => 'Something', 'regular_price' => '100', 'stock' => 3]);

        Livewire::test(Dashboard::class)
            ->assertSet('chartDays', '14')
            ->assertSee('Last 14 days')
            ->call('setChartDays', '7')
            ->assertSet('chartDays', '7')
            ->assertSee('Last 7 days');
    }

    public function test_an_unknown_chart_choice_falls_back_instead_of_breaking(): void
    {
        Livewire::test(Dashboard::class)
            ->call('setChartDays', 'nonsense')
            ->assertSet('chartDays', '14');
    }

    public function test_the_readiness_figure_counts_what_is_actually_done(): void
    {
        Livewire::test(Dashboard::class)->assertSee('steps to go');

        app(ProductService::class)->create([
            'name' => 'First', 'regular_price' => '100', 'status' => Product::STATUS_ACTIVE, 'cost_price' => '50',
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('On sale')
            ->assertSee('Photos');
    }
}
