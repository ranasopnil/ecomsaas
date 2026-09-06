<?php

namespace App\Livewire\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\InventoryLevel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $productCount = Product::count();
        $store = Tenancy::current();

        return view('livewire.admin.dashboard', [
            'store' => $store,
            'productCount' => $productCount,
            'onSaleCount' => Product::onSale()->count(),
            'productAllowance' => Entitlements::limit('products'),
            'productsLeft' => Entitlements::remaining('products', $productCount),
            'outOfStock' => InventoryLevel::where('track_inventory', true)->where('available', '<=', 0)->count(),
            'lowStock' => InventoryLevel::where('track_inventory', true)
                ->whereNotNull('low_stock_threshold')
                ->whereColumn('available', '<=', 'low_stock_threshold')
                ->where('available', '>', 0)
                ->count(),
            'money' => $this->stockMoney($store->currency, $store->currency_exponent),
        ]);
    }

    /**
     * What the stock on the shelf is worth, and what it would make if it all
     * sold at today's prices.
     *
     * Only lines where a cost has been entered are counted, so the figures
     * compare like with like. The shopkeeper is told how many are missing.
     *
     * @return array{cost: Money, retail: Money, profit: Money, margin: float|null, missingCost: int, counted: int}
     */
    protected function stockMoney(string $currency, int $exponent): array
    {
        $totals = ProductVariant::query()
            ->join('inventory_levels', function ($join) {
                $join->on('inventory_levels.product_variant_id', '=', 'product_variants.id')
                    ->on('inventory_levels.tenant_id', '=', 'product_variants.tenant_id');
            })
            ->selectRaw('COALESCE(SUM(GREATEST(inventory_levels.available, 0) * product_variants.cost_price_minor)
                         FILTER (WHERE product_variants.cost_price_minor IS NOT NULL), 0) AS cost_minor')
            ->selectRaw('COALESCE(SUM(GREATEST(inventory_levels.available, 0) * product_variants.price_minor)
                         FILTER (WHERE product_variants.cost_price_minor IS NOT NULL), 0) AS retail_minor')
            ->selectRaw('COUNT(*) FILTER (WHERE product_variants.cost_price_minor IS NULL) AS missing_cost')
            ->selectRaw('COUNT(*) FILTER (WHERE product_variants.cost_price_minor IS NOT NULL) AS counted')
            ->first();

        $cost = (int) ($totals->cost_minor ?? 0);
        $retail = (int) ($totals->retail_minor ?? 0);
        $profit = $retail - $cost;

        return [
            'cost' => new Money($cost, $currency, $exponent),
            'retail' => new Money($retail, $currency, $exponent),
            'profit' => new Money($profit, $currency, $exponent),
            'margin' => $retail > 0 ? round($profit / $retail * 100, 1) : null,
            'missingCost' => (int) ($totals->missing_cost ?? 0),
            'counted' => (int) ($totals->counted ?? 0),
        ];
    }
}
