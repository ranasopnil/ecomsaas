<?php

namespace App\Livewire\Super;

use App\Models\Concerns\TenantScope;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super')]
class PackageForm extends Component
{
    public ?Package $package = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $billing_period = Package::PERIOD_MONTHLY;

    public int $trial_days = 14;

    public bool $is_public = true;

    public bool $is_active = true;

    public int $sort_order = 1;

    /** @var array<string, string> currency code => what a person types, e.g. "990" */
    public array $prices = [];

    /** @var array<string, string> counted feature => ceiling, blank means no ceiling */
    public array $limits = [];

    /** @var array<string, bool> switched feature => on or off */
    public array $switches = [];

    public function mount(?Package $package = null): void
    {
        foreach (array_keys(config('currencies')) as $currency) {
            $this->prices[$currency] = '';
        }

        foreach (config('features') as $feature => $definition) {
            if ($definition['type'] === 'limit') {
                $this->limits[$feature] = '';
            } else {
                $this->switches[$feature] = false;
            }
        }

        if ($package?->exists) {
            $this->package = $package->load(['prices', 'entitlements']);
            $this->fillFrom($this->package);
        }
    }

    protected function fillFrom(Package $package): void
    {
        $this->name = $package->name;
        $this->slug = $package->slug;
        $this->description = (string) $package->description;
        $this->billing_period = $package->billing_period;
        $this->trial_days = $package->trial_days;
        $this->is_public = $package->is_public;
        $this->is_active = $package->is_active;
        $this->sort_order = $package->sort_order;

        foreach ($package->prices as $price) {
            $this->prices[$price->currency] = $price->price->toDecimal();
        }

        foreach ($package->entitlements as $entitlement) {
            $definition = config("features.{$entitlement->feature}");

            if ($definition === null) {
                continue;
            }

            if ($definition['type'] === 'limit') {
                $this->limits[$entitlement->feature] = $entitlement->enabled && $entitlement->limit_value !== null
                    ? (string) $entitlement->limit_value
                    : ($entitlement->enabled ? '' : '0');
            } else {
                $this->switches[$entitlement->feature] = $entitlement->enabled;
            }
        }
    }

    public function updatedName(string $value): void
    {
        if ($this->package === null) {
            $this->slug = Str::slug($value);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('packages', 'slug')->ignore($this->package?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'billing_period' => ['required', Rule::in([Package::PERIOD_MONTHLY, Package::PERIOD_YEARLY])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'limits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ] + $this->ceilingRules();
    }

    /**
     * Features with a platform-wide ceiling refuse anything above it.
     *
     * @return array<string, array<int, string>>
     */
    protected function ceilingRules(): array
    {
        $rules = [];

        foreach (config('features') as $feature => $definition) {
            if (isset($definition['max'])) {
                $rules["limits.{$feature}"] = ['nullable', 'integer', 'min:0', 'max:'.$definition['max']];
            }
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach (config('currencies') as $code => $currency) {
            $attributes["prices.{$code}"] = "{$currency['name']} price";
        }

        foreach (config('features') as $feature => $definition) {
            $attributes["limits.{$feature}"] = mb_strtolower($definition['label']);
        }

        return $attributes;
    }

    public function save()
    {
        $this->validate();

        $given = array_filter($this->prices, fn ($value) => $value !== '' && $value !== null);

        if ($given === []) {
            $this->addError('prices', 'Give the plan a price in at least one currency, or no shop can buy it.');

            return null;
        }

        foreach ($given as $currency => $value) {
            if ((int) config("currencies.{$currency}.exponent") === 0 && str_contains((string) $value, '.')) {
                $this->addError("prices.{$currency}", config("currencies.{$currency}.name").' has no decimal places. Enter a whole number.');

                return null;
            }
        }

        $package = DB::transaction(function () use ($given) {
            $package = $this->package ?? new Package;

            $base = $this->baseMoney($given);

            $package->fill([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description ?: null,
                'billing_period' => $this->billing_period,
                'trial_days' => $this->trial_days,
                'is_public' => $this->is_public,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
                'price_minor' => $base->minor,
                'currency' => $base->currency,
                'currency_exponent' => $base->exponent,
            ])->save();

            $this->syncPrices($package, $given);
            $this->syncEntitlements($package);

            return $package;
        });

        $this->applyToExistingShops($package);

        session()->flash('status', "The {$package->name} plan was saved.");

        return $this->redirectRoute('super.packages.index', navigate: true);
    }

    /**
     * @param  array<string, string>  $given
     */
    protected function baseMoney(array $given): Money
    {
        $currency = array_key_exists('BDT', $given) ? 'BDT' : array_key_first($given);

        return Money::fromDecimal($given[$currency], $currency, (int) config("currencies.{$currency}.exponent"));
    }

    /**
     * @param  array<string, string>  $given
     */
    protected function syncPrices(Package $package, array $given): void
    {
        foreach (array_keys(config('currencies')) as $currency) {
            if (! array_key_exists($currency, $given)) {
                $package->prices()->where('currency', $currency)->delete();

                continue;
            }

            $money = Money::fromDecimal($given[$currency], $currency, (int) config("currencies.{$currency}.exponent"));

            $package->prices()->updateOrCreate(
                ['currency' => $currency],
                [
                    'price_minor' => $money->minor,
                    'currency_exponent' => $money->exponent,
                    'billing_period' => $this->billing_period,
                ],
            );
        }
    }

    protected function syncEntitlements(Package $package): void
    {
        foreach (config('features') as $feature => $definition) {
            if ($definition['type'] === 'limit') {
                $value = $this->limits[$feature] ?? '';

                $package->entitlements()->updateOrCreate(
                    ['feature' => $feature],
                    ['enabled' => true, 'limit_value' => $value === '' ? null : (int) $value],
                );

                continue;
            }

            $package->entitlements()->updateOrCreate(
                ['feature' => $feature],
                ['enabled' => (bool) ($this->switches[$feature] ?? false), 'limit_value' => null],
            );
        }
    }

    /**
     * Allowances change for shops already on the plan. Prices do not: what a
     * shop agreed to pay stays as it was.
     */
    protected function applyToExistingShops(Package $package): void
    {
        $tenantIds = Subscription::withoutGlobalScope(TenantScope::class)
            ->active()
            ->where('package_id', $package->id)
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            return;
        }

        $package = $package->fresh('entitlements');
        $subscribe = app(SubscribeToPackage::class);

        Tenant::whereIn('id', $tenantIds)->get()->each(
            fn (Tenant $tenant) => $subscribe->syncEntitlements($tenant, $package)
        );
    }

    public function render()
    {
        return view('livewire.super.package-form', [
            'currencies' => config('currencies'),
            'features' => config('features'),
            'shopsOnPlan' => $this->package
                ? Subscription::withoutGlobalScope(TenantScope::class)->active()->where('package_id', $this->package->id)->count()
                : 0,
        ])->title($this->package ? 'Edit '.$this->package->name : 'New plan');
    }
}
