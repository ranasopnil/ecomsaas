<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\Product;
use App\Services\Storefront\MapProviders;
use App\Support\GeoPoint;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where the shop delivers.
 *
 * Set once, and every product follows it unless that product says otherwise.
 * A shop that delivers everywhere never has to touch a map.
 */
#[Layout('layouts.admin')]
#[Title('Delivery area')]
class DeliveryAreaForm extends Component
{
    public bool $everywhere = true;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public float $radius = 5;

    public function mount(): void
    {
        $shop = Tenancy::current();

        $this->everywhere = (bool) $shop->delivers_everywhere;
        $this->latitude = $shop->delivery_latitude;
        $this->longitude = $shop->delivery_longitude;
        $this->radius = (float) ($shop->delivery_radius_km ?: 5);
    }

    public function save(): void
    {
        $shop = Tenancy::current();

        if (! $this->everywhere && GeoPoint::tryFrom($this->latitude, $this->longitude) === null) {
            $this->dispatch('toast', [
                'text' => 'Drop a pin on the map first, so we know where you deliver from.',
                'tone' => 'bad',
            ]);

            return;
        }

        if (! $this->everywhere && $this->radius <= 0) {
            $this->dispatch('toast', ['text' => 'Set how far around the pin you deliver.', 'tone' => 'bad']);

            return;
        }

        $shop->forceFill([
            'delivers_everywhere' => $this->everywhere,
            'delivery_latitude' => $this->everywhere ? null : $this->latitude,
            'delivery_longitude' => $this->everywhere ? null : $this->longitude,
            'delivery_radius_km' => $this->everywhere ? null : $this->radius,
        ])->save();

        Tenancy::set($shop->fresh());

        $this->dispatch('toast', [
            'text' => $this->everywhere
                ? 'Your shop now delivers everywhere.'
                : 'Saved. Customers more than '.rtrim(rtrim(number_format($this->radius, 1), '0'), '.')
                    .' km away will not see what follows your shop area.',
            'tone' => 'ok',
        ]);
    }

    public function render()
    {
        $shop = Tenancy::current();
        $maps = app(MapProviders::class);

        return view('livewire.admin.delivery-area-form', [
            'shop' => $shop,
            'provider' => $maps->forShop($shop),
            'mapName' => $maps->nameFor($shop),
            'googleKey' => $maps->key($maps->forShop($shop)) ?? '',
            'centre' => config("countries.{$shop->country_code}.centre", [23.8103, 90.4125]),
            'zoom' => config("countries.{$shop->country_code}.zoom", 11),
            'followers' => Product::where('availability', Product::AVAILABLE_SHOP)->count(),
            'ownArea' => Product::where('availability', Product::AVAILABLE_AREA)->count(),
            'anywhere' => Product::where('availability', Product::AVAILABLE_ANYWHERE)->count(),
        ]);
    }
}
