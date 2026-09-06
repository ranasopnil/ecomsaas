<?php

namespace App\Livewire\Super;

use App\Models\Concerns\TenantScope;
use App\Models\Package;
use App\Models\Subscription;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super')]
#[Title('Plans')]
class PackageIndex extends Component
{
    /**
     * Shown inside this component. A page-level flash would not appear until
     * the next full page load, because Livewire only repaints the component.
     */
    public string $message = '';

    public string $messageType = 'status';

    public function toggleActive(int $packageId): void
    {
        $package = Package::findOrFail($packageId);
        $package->update(['is_active' => ! $package->is_active]);

        $this->say($package->is_active
            ? "{$package->name} is on sale again."
            : "{$package->name} is no longer offered to new shops. Shops already on it keep it.");
    }

    public function delete(int $packageId): void
    {
        $package = Package::findOrFail($packageId);

        $everUsed = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('package_id', $package->id)
            ->exists();

        if ($everUsed) {
            $this->say("{$package->name} has been sold to shops, so it cannot be deleted. Switch it off instead — the record of what was sold must stay.", 'error');

            return;
        }

        $package->delete();

        $this->say("{$package->name} was deleted.");
    }

    protected function say(string $message, string $type = 'status'): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        $packages = Package::with(['prices', 'entitlements'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $shopsPerPackage = Subscription::withoutGlobalScope(TenantScope::class)
            ->active()
            ->selectRaw('package_id, count(*) as shops')
            ->groupBy('package_id')
            ->pluck('shops', 'package_id');

        return view('livewire.super.package-index', [
            'packages' => $packages,
            'shopsPerPackage' => $shopsPerPackage,
        ]);
    }
}
