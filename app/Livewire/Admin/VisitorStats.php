<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Services\Analytics\VisitorReport;
use Livewire\Component;

/**
 * The visitor figures on the shop overview.
 *
 * It sits in its own component so the "right now" number can refresh every few
 * seconds without redrawing the whole dashboard.
 */
class VisitorStats extends Component
{
    public function render(VisitorReport $report)
    {
        $store = Tenancy::current();

        return view('livewire.admin.visitor-stats', [
            'store' => $store,
            'visits' => $report->forStore($store),
        ]);
    }
}
