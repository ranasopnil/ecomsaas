<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\Courier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The couriers a shop hands parcels to.
 *
 * Named once here, then picked from a list on every order rather than typed
 * again each time. A shop that has named none is offered the ones most shops
 * in its country use, and can still add its own.
 */
#[Layout('layouts.admin')]
#[Title('Couriers')]
class CourierIndex extends Component
{
    public ?int $editingId = null;

    public bool $adding = false;

    public string $name = '';

    public string $phone = '';

    public string $tracking_url = '';

    public function add(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'tracking_url']);
        $this->resetErrorBag();

        $this->adding = true;
    }

    public function edit(int $courierId): void
    {
        $courier = Courier::findOrFail($courierId);

        $this->editingId = $courier->id;
        $this->adding = false;
        $this->name = $courier->name;
        $this->phone = (string) $courier->phone;
        $this->tracking_url = (string) $courier->tracking_url;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'adding', 'name', 'phone', 'tracking_url']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tracking_url' => ['nullable', 'string', 'max:300', 'regex:~^https?://~i'],
        ], [
            'tracking_url.regex' => 'A tracking address has to start with http:// or https://.',
        ]);

        $clash = Courier::where('name', trim($this->name))
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($clash) {
            $this->addError('name', 'You already have a courier called that.');

            return;
        }

        $courier = $this->editingId ? Courier::findOrFail($this->editingId) : new Courier;

        $courier->fill([
            'tenant_id' => Tenancy::id(),
            'name' => trim($this->name),
            'phone' => trim($this->phone) ?: null,
            'tracking_url' => trim($this->tracking_url) ?: null,
        ]);

        if (! $courier->exists) {
            $courier->position = (int) Courier::max('position') + 1;
        }

        $courier->save();

        $this->dispatch('toast', ['text' => $courier->name.' was saved.', 'tone' => 'ok']);
        $this->cancel();
    }

    /**
     * The couriers most shops in this country use, added in one press. Each
     * one can then be renamed, changed or removed like any other.
     */
    public function addSuggested(): void
    {
        $country = Tenancy::current()?->country_code ?? '';
        $suggestions = config('couriers.'.$country, []);
        $added = 0;
        $position = (int) Courier::max('position');

        foreach ($suggestions as $suggestion) {
            if (Courier::where('name', $suggestion['name'])->exists()) {
                continue;
            }

            Courier::create([
                'tenant_id' => Tenancy::id(),
                'name' => $suggestion['name'],
                'tracking_url' => $suggestion['tracking_url'] ?? null,
                'position' => ++$position,
            ]);

            $added++;
        }

        $this->dispatch('toast', [
            'text' => $added === 0 ? 'You already have all of those.' : $added.' couriers added.',
            'tone' => 'ok',
        ]);
    }

    public function toggle(int $courierId): void
    {
        $courier = Courier::findOrFail($courierId);
        $courier->update(['is_active' => ! $courier->is_active]);

        $this->dispatch('toast', [
            'text' => $courier->name.($courier->is_active ? ' is back on the list.' : ' is off the list.'),
            'tone' => 'ok',
        ]);
    }

    /**
     * Only a courier no order was ever handed to can be removed. Anything
     * else would leave an order saying it went somewhere that never existed.
     */
    public function delete(int $courierId): void
    {
        $courier = Courier::withCount('orders')->findOrFail($courierId);

        if ($courier->orders_count > 0) {
            $this->dispatch('toast', [
                'text' => $courier->name.' has parcels against it. Switch it off instead.',
                'tone' => 'bad',
            ]);

            return;
        }

        $courier->delete();

        $this->dispatch('toast', ['text' => $courier->name.' was removed.', 'tone' => 'ok']);
    }

    public function render()
    {
        return view('livewire.admin.courier-index', [
            'couriers' => Courier::withCount('orders')->orderBy('position')->orderBy('name')->get(),
            'suggestions' => config('couriers.'.(Tenancy::current()?->country_code ?? ''), []),
        ]);
    }
}
