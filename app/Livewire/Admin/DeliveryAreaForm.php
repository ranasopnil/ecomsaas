<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\DeliveryArea;
use App\Models\Product;
use App\Services\Storefront\MapProviders;
use App\Support\GeoPoint;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The places a shop delivers to, each with a name the shopkeeper chose.
 *
 * Drawn here once — "Dhaka city", "Mirpur", "Uttara" — and then picked from a
 * list on every product, rather than drawn again each time.
 */
#[Layout('layouts.admin')]
#[Title('Delivery areas')]
class DeliveryAreaForm extends Component
{
    /** The area being added or changed; null when the form is closed. */
    public ?int $editingId = null;

    public bool $adding = false;

    public string $name = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public float $radius = 5;

    public function add(): void
    {
        $this->reset(['editingId', 'name', 'latitude', 'longitude']);
        $this->resetErrorBag();

        $this->adding = true;
        $this->radius = 5;
    }

    public function edit(int $areaId): void
    {
        $area = DeliveryArea::findOrFail($areaId);

        $this->editingId = $area->id;
        $this->adding = false;
        $this->name = $area->name;
        $this->latitude = $area->latitude;
        $this->longitude = $area->longitude;
        $this->radius = (float) $area->radius_km;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'adding', 'name', 'latitude', 'longitude', 'radius']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'radius' => ['required', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        if (GeoPoint::tryFrom($this->latitude, $this->longitude) === null) {
            $this->addError('latitude', 'Drop a pin on the map so we know where this area is.');

            return;
        }

        $clash = DeliveryArea::where('name', trim($this->name))
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($clash) {
            $this->addError('name', 'You already have an area called that.');

            return;
        }

        $area = $this->editingId ? DeliveryArea::findOrFail($this->editingId) : new DeliveryArea;

        $area->fill([
            'tenant_id' => Tenancy::id(),
            'name' => trim($this->name),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_km' => $this->radius,
        ]);

        if (! $area->exists) {
            $area->position = (int) DeliveryArea::max('position') + 1;
        }

        $area->save();

        $this->dispatch('toast', ['text' => $area->name.' was saved.', 'tone' => 'ok']);
        $this->cancel();
    }

    public function delete(int $areaId): void
    {
        $area = DeliveryArea::withCount('products')->findOrFail($areaId);

        if ($area->products_count > 0) {
            $this->dispatch('toast', [
                'text' => $area->name.' is still picked on '.$area->products_count
                    .' '.($area->products_count === 1 ? 'product' : 'products').'. Change those first.',
                'tone' => 'bad',
            ]);

            return;
        }

        $name = $area->name;
        $area->delete();

        $this->dispatch('toast', ['text' => $name.' was removed.', 'tone' => 'ok']);
    }

    public function render()
    {
        $shop = Tenancy::current();
        $maps = app(MapProviders::class);
        $areas = DeliveryArea::withCount('products')->orderBy('position')->orderBy('id')->get();

        return view('livewire.admin.delivery-area-form', [
            'shop' => $shop,
            'areas' => $areas,
            'provider' => $maps->forShop($shop),
            'mapName' => $maps->nameFor($shop),
            'googleKey' => $maps->key($maps->forShop($shop)) ?? '',
            'centre' => config("countries.{$shop->country_code}.centre", [23.8103, 90.4125]),
            'zoom' => config("countries.{$shop->country_code}.zoom", 11),
            'followers' => Product::where('availability', Product::AVAILABLE_SHOP)->count(),
            'tied' => Product::where('availability', Product::AVAILABLE_AREAS)->count(),
            'anywhere' => Product::where('availability', Product::AVAILABLE_ANYWHERE)->count(),
        ]);
    }
}
