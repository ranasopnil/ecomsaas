<?php

namespace App\Livewire\Super;

use App\Models\Concerns\TenantScope;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.super')]
#[Title('Shops')]
class StoreIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $message = '';

    public string $messageType = 'status';

    /** @var array<int, string> shop id => chosen plan slug */
    public array $planChoice = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function changePlan(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $package = Package::where('slug', $this->planChoice[$tenantId] ?? '')->first();

        if ($package === null) {
            $this->say('Choose a plan first.', 'error');

            return;
        }

        try {
            app(SubscribeToPackage::class)->handle($tenant, $package);
        } catch (Exception $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }

        $this->say("{$tenant->name} is now on the {$package->name} plan.");
    }

    protected function say(string $message, string $type = 'status'): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        $stores = Tenant::query()
            ->when($this->search !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'ilike', '%'.$this->search.'%')
                    ->orWhere('slug', 'ilike', '%'.$this->search.'%')))
            ->orderByDesc('id')
            ->paginate(20);

        $subscriptions = Subscription::withoutGlobalScope(TenantScope::class)
            ->active()
            ->with('package')
            ->whereIn('tenant_id', $stores->pluck('id'))
            ->get()
            ->keyBy('tenant_id');

        return view('livewire.super.store-index', [
            'stores' => $stores,
            'subscriptions' => $subscriptions,
            'packages' => Package::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
