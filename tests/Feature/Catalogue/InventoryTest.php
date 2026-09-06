<?php

namespace Tests\Feature\Catalogue;

use App\Exceptions\ImmutableFinancialRecord;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\InventoryMovement;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\InventoryService;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function shop(): Tenant
    {
        $store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($store, Package::factory()->allowing(['products' => 100])->create());
        Entitlements::forget($store->id);

        return $store;
    }

    public function test_holding_stock_takes_it_out_of_what_is_available(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Mug', 'price' => '250', 'stock' => 10])
                ->variants->first();

            $inventory = app(InventoryService::class);

            $this->assertTrue($inventory->reserve($variant, 3, 'order-1'));

            $level = $inventory->levelFor($variant)->fresh();
            $this->assertSame(7, $level->available);
            $this->assertSame(3, $level->reserved);
        });
    }

    public function test_the_last_item_can_only_be_sold_once(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Last One', 'price' => '999', 'stock' => 1])
                ->variants->first();

            $inventory = app(InventoryService::class);

            $this->assertTrue($inventory->reserve($variant, 1, 'shopper-a'));
            $this->assertFalse($inventory->reserve($variant, 1, 'shopper-b'));

            $level = $inventory->levelFor($variant)->fresh();
            $this->assertSame(0, $level->available);
            $this->assertSame(1, $level->reserved);
        });
    }

    public function test_asking_for_more_than_there_is_changes_nothing(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Few', 'price' => '100', 'stock' => 2])
                ->variants->first();

            $inventory = app(InventoryService::class);

            $this->assertFalse($inventory->reserve($variant, 5));

            $level = $inventory->levelFor($variant)->fresh();
            $this->assertSame(2, $level->available);
            $this->assertSame(0, $level->reserved);
        });
    }

    public function test_a_cancelled_order_puts_the_stock_back(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Cap', 'price' => '300', 'stock' => 5])
                ->variants->first();

            $inventory = app(InventoryService::class);
            $inventory->reserve($variant, 2, 'order-9');
            $inventory->release($variant, 2, 'order-9');

            $level = $inventory->levelFor($variant)->fresh();
            $this->assertSame(5, $level->available);
            $this->assertSame(0, $level->reserved);
        });
    }

    public function test_a_despatched_order_does_not_put_stock_back(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Box', 'price' => '400', 'stock' => 5])
                ->variants->first();

            $inventory = app(InventoryService::class);
            $inventory->reserve($variant, 2, 'order-10');
            $inventory->fulfil($variant, 2, 'order-10');

            $level = $inventory->levelFor($variant)->fresh();
            $this->assertSame(3, $level->available);
            $this->assertSame(0, $level->reserved);
        });
    }

    public function test_a_shop_that_does_not_count_stock_can_always_sell(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Made To Order', 'price' => '1500', 'track_inventory' => false])
                ->variants->first();

            $this->assertTrue(app(InventoryService::class)->reserve($variant, 99));
        });
    }

    public function test_a_shop_that_allows_backorders_can_go_below_zero(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Preorder', 'price' => '2500', 'stock' => 1, 'allow_backorder' => true])
                ->variants->first();

            $inventory = app(InventoryService::class);
            $this->assertTrue($inventory->reserve($variant, 3));
            $this->assertSame(-2, $inventory->levelFor($variant)->fresh()->available);
        });
    }

    public function test_every_stock_change_is_written_down(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Tracked', 'price' => '100', 'stock' => 10])
                ->variants->first();

            $inventory = app(InventoryService::class);
            $inventory->reserve($variant, 4, 'order-3');
            $inventory->receive($variant, 20, InventoryMovement::REASON_RECEIVED, 'delivery-7');

            $movements = InventoryMovement::orderBy('id')->get();

            $this->assertSame([10, -4, 20], $movements->pluck('quantity_change')->all());
            $this->assertSame([10, 6, 26], $movements->pluck('available_after')->all());
        });
    }

    public function test_stock_history_cannot_be_rewritten(): void
    {
        $store = $this->shop();

        Tenancy::run($store, function () {
            $variant = app(ProductService::class)
                ->create(['name' => 'Audited', 'price' => '100', 'stock' => 3])
                ->variants->first();

            $movement = InventoryMovement::firstOrFail();

            $this->expectException(ImmutableFinancialRecord::class);

            $movement->update(['quantity_change' => 999]);
        });
    }

    public function test_one_shop_cannot_touch_another_shops_stock(): void
    {
        [$shopA, $shopB] = [$this->shop(), $this->shop()];

        $variant = Tenancy::run($shopA, fn () => app(ProductService::class)
            ->create(['name' => 'Guarded', 'price' => '100', 'stock' => 5])
            ->variants->first());

        Tenancy::run($shopB, function () use ($variant) {
            // The other shop's stock row is invisible, so a level is made for
            // this shop instead of the neighbour's being changed.
            $this->assertSame(0, app(InventoryService::class)->levelFor($variant)->available);
        });

        Tenancy::run($shopA, fn () => $this->assertSame(5, app(InventoryService::class)->levelFor($variant)->fresh()->available));
    }
}
