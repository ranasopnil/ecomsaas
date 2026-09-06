<?php

namespace App\Livewire\Super;

use App\Models\Concerns\TenantScope;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super')]
#[Title('Overview')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.super.dashboard', [
            'storeCount' => Tenant::count(),
            'activeStoreCount' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'packageCount' => Package::count(),
            'planTotals' => $this->planTotals(),
        ]);
    }

    /**
     * How many shops are on each plan. Reading across every shop is a
     * super-admin job, so the tenant scope is dropped deliberately here.
     *
     * @return Collection<int, object>
     */
    protected function planTotals()
    {
        return Subscription::withoutGlobalScope(TenantScope::class)
            ->active()
            ->select('package_id', DB::raw('count(*) as shops'))
            ->groupBy('package_id')
            ->with('package')
            ->get();
    }
}
