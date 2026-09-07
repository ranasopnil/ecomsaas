<?php

namespace App\Livewire\Super;

use App\Models\Tenant;
use App\Services\Storefront\MapProviders;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Which map each shop draws its delivery area on. One of them costs the
 * platform money per map opened, so it is handed out rather than chosen.
 */
#[Layout('layouts.super')]
#[Title('Maps')]
class MapAccess extends Component
{
    public string $message = '';

    public function assign(int $tenantId, string $provider): void
    {
        $shop = Tenant::find($tenantId);
        $maps = app(MapProviders::class);

        if ($shop === null) {
            return;
        }

        // An empty choice means "whatever the platform default is".
        $chosen = $provider === '' ? null : $provider;

        if (! $maps->setForShop($shop, $chosen)) {
            $this->message = $maps->find($provider)['name'].' cannot be handed out until its key is set up on the server.';

            return;
        }

        $this->message = $shop->name.' now uses '.$maps->nameFor($shop->fresh()).'.';
    }

    public function render()
    {
        $maps = app(MapProviders::class);

        return view('livewire.super.map-access', [
            'shops' => Tenant::orderBy('name')->get(),
            'providers' => $maps->all(),
            'maps' => $maps,
        ]);
    }
}
