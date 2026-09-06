<?php

namespace App\Livewire\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\InventoryLevel;
use App\Models\Product;
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

        return view('livewire.admin.dashboard', [
            'store' => Tenancy::current(),
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
        ]);
    }
}
