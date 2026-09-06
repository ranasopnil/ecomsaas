<?php

namespace App\Livewire\Super;

use App\Services\Payments\GatewayCatalogue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super')]
#[Title('Payment gateways')]
class GatewayMatrix extends Component
{
    public string $message = '';

    public function toggle(string $gateway, string $country): void
    {
        $catalogue = app(GatewayCatalogue::class);

        if ($catalogue->find($gateway) === null || ! array_key_exists($country, config('countries'))) {
            return;
        }

        $now = ! $catalogue->isAllowedIn($gateway, $country);
        $catalogue->setAllowed($gateway, $country, $now);

        $this->message = $catalogue->find($gateway)['name'].' is now '
            .($now ? 'allowed' : 'not allowed').' in '.config("countries.{$country}.name").'.';
    }

    public function render()
    {
        return view('livewire.super.gateway-matrix', [
            'gateways' => app(GatewayCatalogue::class)->all(),
            'countries' => config('countries'),
            'grid' => app(GatewayCatalogue::class)->grid(),
        ]);
    }
}
