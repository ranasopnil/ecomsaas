<?php

namespace App\Livewire\Admin;

use App\Exceptions\GatewayFailed;
use App\Facades\Tenancy;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayCatalogue;
use App\Services\Payments\GatewayFactory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Payments')]
class PaymentMethodsIndex extends Component
{
    /** The gateway whose details are open for editing. */
    public ?string $editing = null;

    /** @var array<string, mixed> field => typed value (secrets: only a new one being typed) */
    public array $form = [];

    public string $display_name = '';

    public ?string $justChanged = null;

    public function edit(string $gateway): void
    {
        $definition = app(GatewayCatalogue::class)->find($gateway);

        if ($definition === null || ! app(GatewayCatalogue::class)->isAvailableFor(Tenancy::current(), $gateway)) {
            return;
        }

        $method = PaymentMethod::where('gateway', $gateway)->first();

        $this->editing = $gateway;
        $this->display_name = (string) ($method?->display_name ?? '');
        $this->form = [];

        foreach ($definition['fields'] as $key => $field) {
            // Secrets are never loaded into the form. Only a replacement is typed.
            $this->form[$key] = $field['secret']
                ? ''
                : ($method?->settings[$key] ?? (($field['type'] ?? 'text') === 'checkbox' ? false : ''));
        }
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'form', 'display_name']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        if ($this->editing === null) {
            return;
        }

        $definition = app(GatewayCatalogue::class)->find($this->editing);
        $method = PaymentMethod::firstOrNew(['gateway' => $this->editing]);
        $settings = $method->settings ?? [];

        foreach ($definition['fields'] as $key => $field) {
            $value = $this->form[$key] ?? null;

            if ($field['secret']) {
                $method->putSecret($key, is_string($value) ? trim($value) : null);

                continue;
            }

            $settings[$key] = ($field['type'] ?? 'text') === 'checkbox'
                ? (bool) $value
                : (is_string($value) ? trim($value) : $value);
        }

        $method->settings = $settings;
        $method->display_name = trim($this->display_name) ?: null;
        $method->tenant_id = Tenancy::id();

        if (! $method->exists) {
            $method->position = (int) PaymentMethod::max('position') + 1;
        }

        $method->save();

        $this->justChanged = $this->editing;
        $this->cancel();

        $this->dispatch('toast', [
            'text' => $method->isComplete()
                ? $method->name().' saved. Switch it on when you are ready.'
                : $method->name().' saved, but some details are still missing.',
            'tone' => 'ok',
        ]);
    }

    public function toggle(string $gateway): void
    {
        $method = PaymentMethod::where('gateway', $gateway)->first();
        $catalogue = app(GatewayCatalogue::class);
        $available = $catalogue->availableFor(Tenancy::current())->get($gateway);

        if ($method === null || $available === null) {
            $this->dispatch('toast', ['text' => 'Enter the details first.', 'tone' => 'bad']);

            return;
        }

        if (! $method->is_enabled) {
            if (! $available['covered_by_plan']) {
                $this->dispatch('toast', ['text' => 'Your plan does not include this. Upgrade to switch it on.', 'tone' => 'bad']);

                return;
            }

            if (! $method->isComplete()) {
                $this->dispatch('toast', ['text' => 'Some details are missing. Fill them in first.', 'tone' => 'bad']);

                return;
            }
        }

        $method->update(['is_enabled' => ! $method->is_enabled]);

        $this->justChanged = $gateway;
        $this->dispatch('toast', [
            'text' => $method->name().($method->is_enabled ? ' is on. Customers can pay this way.' : ' is off.'),
            'tone' => 'ok',
        ]);
    }

    /**
     * Prove the saved details actually work, by asking the gateway for a token
     * and nothing else. It moves no money and changes nothing.
     */
    public function test(string $gateway): void
    {
        $method = PaymentMethod::where('gateway', $gateway)->first();

        if ($method === null || ! app(GatewayFactory::class)->isDriven($gateway)) {
            return;
        }

        if (! $method->isComplete()) {
            $this->dispatch('toast', ['text' => 'Fill in every detail first, then test.', 'tone' => 'bad']);

            return;
        }

        try {
            app(GatewayFactory::class)->for($method)->testConnection();
        } catch (GatewayFailed $e) {
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);

            return;
        }

        $this->justChanged = $gateway;
        $this->dispatch('toast', [
            'text' => $method->name().' accepted your details'
                .(($method->settings['sandbox'] ?? false) ? ' on the test system.' : '.'),
            'tone' => 'ok',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.payment-methods-index', [
            'available' => app(GatewayCatalogue::class)->availableFor(Tenancy::current()),
            'methods' => PaymentMethod::query()->get()->keyBy('gateway'),
            'definition' => $this->editing ? app(GatewayCatalogue::class)->find($this->editing) : null,
            'factory' => app(GatewayFactory::class),
        ]);
    }
}
