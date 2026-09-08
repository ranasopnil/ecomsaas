<?php

namespace App\Livewire\Super;

use App\Models\Addon;
use App\Models\AddonPrice;
use App\Models\Concerns\TenantScope;
use App\Models\SubscriptionAddon;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The extras shops can buy on top of their plans.
 *
 * Priced separately in every market, exactly as plans are: a figure converted
 * at today's exchange rate is not a commercial decision.
 */
#[Layout('layouts.super')]
#[Title('Add-ons')]
class AddonIndex extends Component
{
    public ?int $editingId = null;

    public bool $adding = false;

    public string $name = '';

    public string $description = '';

    public string $kind = Addon::KIND_UNITS;

    public string $feature = 'products';

    public string $unit_amount = '50';

    public bool $is_active = true;

    /** currency => price as typed */
    public array $prices = [];

    public string $message = '';

    public string $messageType = 'status';

    public function add(): void
    {
        $this->reset(['editingId', 'name', 'description', 'prices']);
        $this->resetErrorBag();

        $this->adding = true;
        $this->kind = Addon::KIND_UNITS;
        $this->feature = 'products';
        $this->unit_amount = '50';
        $this->is_active = true;
        $this->prices = array_fill_keys(array_keys(config('currencies')), '');
    }

    public function edit(int $addonId): void
    {
        $addon = Addon::with('prices')->findOrFail($addonId);

        $this->editingId = $addon->id;
        $this->adding = false;
        $this->name = $addon->name;
        $this->description = (string) $addon->description;
        $this->kind = $addon->kind;
        $this->feature = $addon->feature;
        $this->unit_amount = (string) ($addon->unit_amount ?? '');
        $this->is_active = $addon->is_active;
        $this->resetErrorBag();

        $this->prices = [];

        foreach (array_keys(config('currencies')) as $currency) {
            $this->prices[$currency] = $addon->priceIn($currency)?->toDecimal() ?? '';
        }
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'adding', 'name', 'description', 'prices']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:160'],
            'kind' => ['required', Rule::in([Addon::KIND_UNITS, Addon::KIND_SWITCH])],
            'feature' => ['required', Rule::in(array_keys(config('features')))],
            'unit_amount' => [Rule::requiredIf($this->kind === Addon::KIND_UNITS), 'nullable', 'integer', 'min:1'],
        ]);

        // An add-on has to match the shape of what it adds to: you cannot buy
        // "more" of a thing that is only on or off.
        $shouldBe = config('features.'.$this->feature.'.type');

        if ($shouldBe === 'switch' && $this->kind === Addon::KIND_UNITS) {
            $this->addError('kind', 'That feature is on or off, so it cannot be sold in units.');

            return;
        }

        if ($shouldBe === 'limit' && $this->kind === Addon::KIND_SWITCH) {
            $this->addError('kind', 'That feature is a counted thing, so sell it in units.');

            return;
        }

        $addon = $this->editingId ? Addon::findOrFail($this->editingId) : new Addon;

        $addon->fill([
            'name' => trim($this->name),
            'slug' => $addon->slug ?: Str::slug(trim($this->name)).'-'.Str::lower(Str::random(4)),
            'description' => trim($this->description) ?: null,
            'kind' => $this->kind,
            'feature' => $this->feature,
            'unit_amount' => $this->kind === Addon::KIND_UNITS ? (int) $this->unit_amount : null,
            'is_active' => $this->is_active,
        ]);

        if (! $addon->exists) {
            $addon->sort_order = (int) Addon::max('sort_order') + 1;
        }

        $addon->save();

        foreach ($this->prices as $currency => $typed) {
            $typed = trim((string) $typed);
            $exponent = (int) config("currencies.{$currency}.exponent", 2);

            if ($typed === '') {
                AddonPrice::where('addon_id', $addon->id)->where('currency', $currency)->delete();

                continue;
            }

            if (! preg_match('/^\d+(\.\d+)?$/', $typed)) {
                $this->addError('prices.'.$currency, 'Write it in figures, like 300.');

                continue;
            }

            AddonPrice::updateOrCreate(
                ['addon_id' => $addon->id, 'currency' => $currency],
                [
                    'currency_exponent' => $exponent,
                    'price_minor' => Money::fromDecimal($typed, $currency, $exponent)->minor,
                ],
            );
        }

        $this->say($addon->name.' saved.');
        $this->cancel();
    }

    public function toggle(int $addonId): void
    {
        $addon = Addon::findOrFail($addonId);
        $addon->update(['is_active' => ! $addon->is_active]);

        $this->say($addon->name.($addon->is_active ? ' is on sale.' : ' is off sale. Shops that have it keep it.'));
    }

    protected function say(string $message, string $type = 'status'): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        $addons = Addon::with('prices')->orderBy('sort_order')->orderBy('name')->get();

        // How many shops have each one. Staff work across shops, so this
        // deliberately steps outside the per-shop rule.
        $sold = SubscriptionAddon::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', SubscriptionAddon::STATUS_ACTIVE)
            ->selectRaw('addon_id, count(*) as shops')
            ->groupBy('addon_id')
            ->pluck('shops', 'addon_id');

        return view('livewire.super.addon-index', [
            'addons' => $addons,
            'sold' => $sold,
            'currencies' => config('currencies'),
            'features' => config('features'),
        ]);
    }
}
