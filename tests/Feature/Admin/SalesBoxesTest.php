<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\Dashboard;
use App\Models\InventoryMovement;
use App\Models\Package;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SalesBoxesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'timezone' => 'Asia/Dhaka',
        ]);

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

    protected function variant(string $price = '1000', string $cost = '600'): ProductVariant
    {
        return app(ProductService::class)
            ->create(['name' => 'Thing '.uniqid(), 'regular_price' => $price, 'cost_price' => $cost, 'stock' => 100])
            ->variants->first();
    }

    protected function sell(ProductVariant $variant, int $units, Carbon $at): void
    {
        $movement = new InventoryMovement([
            'product_variant_id' => $variant->getKey(),
            'quantity_change' => -$units,
            'available_after' => 0,
            'reason' => InventoryMovement::REASON_SOLD,
            'note' => 'Sold in the shop',
        ]);

        $movement->created_at = $at;
        $movement->save();
    }

    public function test_it_totals_what_was_sold_today(): void
    {
        $variant = $this->variant('1000', '600');

        $this->sell($variant, 3, Carbon::now($this->store->timezone)->startOfDay()->addHours(10)->utc());

        Livewire::test(Dashboard::class)
            ->assertSee('Sold today')
            ->assertSee('BDT 3,000.00')   // 3 x 1000
            ->assertSee('BDT 1,200.00')   // 3 x (1000 - 600)
            ->assertSee('3 items left the shelf');
    }

    public function test_it_keeps_today_and_yesterday_apart(): void
    {
        $variant = $this->variant('1000', '600');
        $midnight = Carbon::now($this->store->timezone)->startOfDay();

        $this->sell($variant, 2, $midnight->copy()->addHours(9)->utc());
        $this->sell($variant, 5, $midnight->copy()->subHours(3)->utc());

        Livewire::test(Dashboard::class)
            ->assertSee('BDT 2,000.00')   // today: 2 x 1000
            ->assertSee('BDT 5,000.00')   // yesterday: 5 x 1000
            ->assertSee('2 items left the shelf')
            ->assertSee('5 items the day before');
    }

    public function test_a_day_ends_at_midnight_in_the_shops_own_timezone(): void
    {
        $variant = $this->variant('1000', '600');

        // Half past midnight in Dhaka is still the previous day in London, so
        // a shop in Dhaka must see this as today.
        $justAfterMidnight = Carbon::now('Asia/Dhaka')->startOfDay()->addMinutes(30);

        $this->sell($variant, 4, $justAfterMidnight->copy()->utc());

        Livewire::test(Dashboard::class)->assertSee('4 items left the shelf');
    }

    public function test_it_shows_whether_today_is_up_or_down_on_yesterday(): void
    {
        $variant = $this->variant('1000', '600');
        $midnight = Carbon::now($this->store->timezone)->startOfDay();

        $this->sell($variant, 2, $midnight->copy()->addHours(9)->utc());
        $this->sell($variant, 4, $midnight->copy()->subHours(3)->utc());

        // Half of yesterday's takings, so a fall of 50%.
        Livewire::test(Dashboard::class)
            ->assertSee('50%')
            ->assertSee('bg-rose-50 text-rose-700', false);
    }

    public function test_stock_arriving_is_not_counted_as_a_sale(): void
    {
        $variant = $this->variant('1000', '600');

        $movement = new InventoryMovement([
            'product_variant_id' => $variant->getKey(),
            'quantity_change' => 50,
            'available_after' => 150,
            'reason' => InventoryMovement::REASON_RECEIVED,
            'note' => 'Delivery received',
        ]);
        $movement->created_at = Carbon::now($this->store->timezone)->startOfDay()->addHours(11)->utc();
        $movement->save();

        Livewire::test(Dashboard::class)->assertSee('0 items left the shelf');
    }

    public function test_a_quiet_day_says_so_rather_than_showing_nothing(): void
    {
        $this->variant();

        Livewire::test(Dashboard::class)
            ->assertSee('Sold today')
            ->assertSee('Nothing sold yet today')
            ->assertSee('BDT 0.00');
    }

    public function test_profit_leaves_out_anything_with_no_cost_entered(): void
    {
        $costed = $this->variant('1000', '600');
        $uncosted = app(ProductService::class)
            ->create(['name' => 'No Cost', 'regular_price' => '500', 'stock' => 10])
            ->variants->first();

        $at = Carbon::now($this->store->timezone)->startOfDay()->addHours(10)->utc();
        $this->sell($costed, 1, $at);
        $this->sell($uncosted, 1, $at);

        Livewire::test(Dashboard::class)
            ->assertSee('BDT 1,500.00')   // sales: 1000 + 500
            ->assertSee('BDT 400.00');    // profit: only the costed one
    }

    public function test_one_shop_never_sees_another_shops_takings(): void
    {
        $variant = $this->variant('1000', '600');
        $this->sell($variant, 9, Carbon::now($this->store->timezone)->startOfDay()->addHours(10)->utc());

        $other = Tenant::factory()->create(['currency' => 'BDT', 'timezone' => 'Asia/Dhaka']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Tenancy::run($other, function () use ($other) {
            $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));

            Livewire::test(Dashboard::class)
                ->assertSee('0 items left the shelf')
                ->assertDontSee('BDT 9,000.00');
        });
    }
}
