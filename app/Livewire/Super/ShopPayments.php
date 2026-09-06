<?php

namespace App\Livewire\Super;

use App\Facades\Tenancy;
use App\Models\Concerns\TenantScope;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantGatewayGrant;
use App\Services\Payments\GatewayCatalogue;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super')]
class ShopPayments extends Component
{
    public Tenant $tenant;

    public string $message = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Hand this shop a gateway its country does not normally get, or take
     * it back. Taking it back also switches off anything the shop set up.
     */
    public function toggleGrant(string $gateway): void
    {
        $catalogue = app(GatewayCatalogue::class);

        if ($catalogue->find($gateway) === null) {
            return;
        }

        Tenancy::run($this->tenant, function () use ($gateway, $catalogue) {
            $existing = TenantGatewayGrant::where('gateway', $gateway)->first();

            if ($existing) {
                $existing->delete();

                PaymentMethod::where('gateway', $gateway)->update(['is_enabled' => false]);

                $this->message = $catalogue->find($gateway)['name'].' taken back from '.$this->tenant->name.'.';

                return;
            }

            TenantGatewayGrant::create([
                'tenant_id' => $this->tenant->id,
                'gateway' => $gateway,
                'granted_by' => auth('admin')->id(),
            ]);

            $this->message = $catalogue->find($gateway)['name'].' handed to '.$this->tenant->name.'.';
        });
    }

    public function render()
    {
        $catalogue = app(GatewayCatalogue::class);

        $grants = TenantGatewayGrant::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant->id)
            ->pluck('gateway')->all();

        $setUp = PaymentMethod::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant->id)
            ->get()->keyBy('gateway');

        return view('livewire.super.shop-payments', [
            'gateways' => $catalogue->all(),
            'grants' => $grants,
            'setUp' => $setUp,
            'country' => config("countries.{$this->tenant->country_code}.name", $this->tenant->country_code),
            'byCountry' => $catalogue->all()->keys()
                ->mapWithKeys(fn ($key) => [$key => $catalogue->isAllowedIn($key, $this->tenant->country_code)])
                ->all(),
        ])->title($this->tenant->name.' — payments');
    }
}
