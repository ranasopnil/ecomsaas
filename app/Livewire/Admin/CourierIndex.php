<?php

namespace App\Livewire\Admin;

use App\Exceptions\CourierFailed;
use App\Facades\Tenancy;
use App\Models\Courier;
use App\Services\Couriers\CourierFactory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The couriers a shop hands parcels to.
 *
 * Named once here, then picked from a list on every order rather than typed
 * again each time. A courier can be left as a name — the shopkeeper types the
 * consignment number in themselves — or, for the ones this platform can talk
 * to, switched to automatic: then the parcel is booked as the order is handed
 * over and the number comes back on its own.
 *
 * Account details are the shop's property: encrypted, never shown back, and
 * shown afterwards only as their last four characters.
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

    /** Which courier module, or '' for one we cannot talk to. */
    public string $driver = '';

    public string $mode = Courier::MANUAL;

    /** @var array<string, mixed> field => typed value (secrets: only a new one being typed) */
    public array $form = [];

    public ?int $justChanged = null;

    public function add(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'tracking_url', 'driver', 'form']);
        $this->resetErrorBag();

        $this->adding = true;
        $this->mode = Courier::MANUAL;
    }

    public function edit(int $courierId): void
    {
        $courier = Courier::findOrFail($courierId);

        $this->editingId = $courier->id;
        $this->adding = false;
        $this->name = $courier->name;
        $this->phone = (string) $courier->phone;
        $this->tracking_url = (string) $courier->tracking_url;
        $this->driver = (string) $courier->driver;
        $this->mode = $courier->mode ?: Courier::MANUAL;
        $this->form = [];
        $this->resetErrorBag();

        $this->fillForm($courier);
    }

    /**
     * Choosing a module changes what has to be filled in, so the form is
     * rebuilt around it. Secrets are never loaded into it.
     */
    public function updatedDriver(): void
    {
        $this->form = [];

        if ($this->driver === '') {
            $this->mode = Courier::MANUAL;

            return;
        }

        $this->fillForm($this->editingId ? Courier::find($this->editingId) : null);

        // A courier we can talk to starts with its own tracking address, so
        // customers get a working link without anybody looking it up.
        if (trim($this->tracking_url) === '') {
            $this->tracking_url = (string) config('couriers.drivers.'.$this->driver.'.tracking_url', '');
        }
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'adding', 'name', 'phone', 'tracking_url', 'driver', 'mode', 'form']);
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
        $settings = $courier->settings ?? [];

        foreach ($this->fields() as $key => $field) {
            $value = $this->form[$key] ?? null;

            if ($field['secret']) {
                $courier->putSecret($key, is_string($value) ? trim($value) : null);

                continue;
            }

            $settings[$key] = ($field['type'] ?? 'text') === 'checkbox'
                ? (bool) $value
                : (is_string($value) ? trim($value) : $value);
        }

        $courier->fill([
            'tenant_id' => Tenancy::id(),
            'name' => trim($this->name),
            'phone' => trim($this->phone) ?: null,
            'tracking_url' => trim($this->tracking_url) ?: null,
            'driver' => $this->driver ?: null,
            'mode' => $this->driver === '' ? Courier::MANUAL : $this->mode,
        ]);

        $courier->settings = $settings;

        if (! $courier->exists) {
            $courier->position = (int) Courier::max('position') + 1;
        }

        $courier->save();

        if ($courier->isAutomatic() && ! $courier->isComplete()) {
            $this->dispatch('toast', [
                'text' => $courier->name.' was saved, but some details are still missing, so parcels will not be booked yet.',
                'tone' => 'bad',
            ]);
        } else {
            $this->dispatch('toast', ['text' => $courier->name.' was saved.', 'tone' => 'ok']);
        }

        $this->justChanged = $courier->id;
        $this->cancel();
    }

    /**
     * Prove the saved details work, by asking the courier something that
     * books nothing and changes nothing.
     */
    public function test(int $courierId): void
    {
        $courier = Courier::findOrFail($courierId);

        if (! $courier->canBeAutomatic()) {
            return;
        }

        if (! $courier->isComplete()) {
            $this->dispatch('toast', ['text' => 'Fill in every detail first, then test.', 'tone' => 'bad']);

            return;
        }

        try {
            app(CourierFactory::class)->for($courier)->testConnection();
        } catch (CourierFailed $e) {
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);

            return;
        }

        $this->justChanged = $courier->id;
        $this->dispatch('toast', [
            'text' => $courier->name.' accepted your details'.($courier->isTestMode() ? ' on the test system.' : '.'),
            'tone' => 'ok',
        ]);
    }

    /**
     * The couriers most shops in this country use, added in one press. Each
     * one can then be renamed, changed or removed like any other.
     */
    public function addSuggested(): void
    {
        $country = Tenancy::current()?->country_code ?? '';
        $added = 0;
        $position = (int) Courier::max('position');

        foreach (config('couriers.suggestions.'.$country, []) as $suggestion) {
            if (Courier::where('name', $suggestion['name'])->exists()) {
                continue;
            }

            $driver = $suggestion['driver'] ?? null;

            Courier::create([
                'tenant_id' => Tenancy::id(),
                'name' => $suggestion['name'],
                'driver' => $driver,
                // Added as a name only. Talking to a courier needs the shop's
                // own account, so nobody is switched to automatic behind
                // their back.
                'mode' => Courier::MANUAL,
                'tracking_url' => $suggestion['tracking_url']
                    ?? config('couriers.drivers.'.$driver.'.tracking_url'),
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

    /**
     * What the chosen module needs filled in.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return $this->driver === ''
            ? []
            : config('couriers.drivers.'.$this->driver.'.fields', []);
    }

    protected function fillForm(?Courier $courier): void
    {
        foreach ($this->fields() as $key => $field) {
            // Secrets are never loaded into the form. Only a replacement is typed.
            $this->form[$key] = $field['secret']
                ? ''
                : ($courier?->settings[$key] ?? (($field['type'] ?? 'text') === 'checkbox' ? false : ''));
        }
    }

    public function render()
    {
        return view('livewire.admin.courier-index', [
            'couriers' => Courier::withCount('orders')->orderBy('position')->orderBy('name')->get(),
            'suggestions' => config('couriers.suggestions.'.(Tenancy::current()?->country_code ?? ''), []),
            'modules' => app(CourierFactory::class)->catalogue(),
            'editing' => $this->editingId ? Courier::find($this->editingId) : null,
        ]);
    }
}
