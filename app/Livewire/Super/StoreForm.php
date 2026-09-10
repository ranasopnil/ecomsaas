<?php

namespace App\Livewire\Super;

use App\Facades\Tenancy;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\User;
use App\Services\Stores\ProvisionStore;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Opening a shop for somebody.
 *
 * The same thing the `store:create` command has always done, with a screen in
 * front of it so it does not need somebody at a terminal. The shop, its free
 * address, its plan and the owner's sign-in are all made together, and the
 * whole thing is written to the audit log.
 */
#[Layout('layouts.super')]
#[Title('Add a shop')]
class StoreForm extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $country = 'BD';

    public string $currency = 'BDT';

    public string $plan = '';

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPassword = '';

    /** Shown once, after the shop is made. */
    public ?string $madeName = null;

    public ?string $madeAddress = null;

    public function mount(): void
    {
        $this->plan = (string) (Package::query()->where('is_active', true)->orderBy('sort_order')->value('slug') ?? '');
    }

    /** Following the country keeps the plan priced in the money the shop takes. */
    public function updatedCountry(string $code): void
    {
        $this->currency = config("countries.{$code}.currency", $this->currency);
    }

    public function save(ProvisionStore $provisioner): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('tenants', 'slug')],
            'country' => ['required', Rule::in(array_keys(config('countries')))],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'plan' => ['required', Rule::exists('packages', 'slug')->whereNull('deleted_at')],
            'ownerName' => ['required', 'string', 'max:120'],
            'ownerEmail' => ['required', 'email', 'max:190'],
            'ownerPassword' => ['required', 'string', 'min:12'],
        ], attributes: [
            'ownerName' => "the owner's name",
            'ownerEmail' => "the owner's email",
            'ownerPassword' => "the owner's password",
        ]);

        $package = Package::where('slug', $data['plan'])->firstOrFail();

        $tenant = $provisioner->handle(
            $data['name'],
            $data['slug'] !== '' ? $data['slug'] : null,
            $package,
            [
                'country_code' => $data['country'],
                'currency' => $data['currency'],
                'currency_exponent' => (int) config("currencies.{$data['currency']}.exponent", 2),
                'email' => $data['ownerEmail'],
            ],
        );

        // The owner's own sign-in, inside their own shop.
        Tenancy::run($tenant, fn () => User::create([
            'name' => $data['ownerName'],
            'email' => $data['ownerEmail'],
            'password' => $data['ownerPassword'],
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]));

        AuditLog::record('shop.created', $tenant, $tenant, 'Opened the shop and made the owner a sign-in', [
            'plan' => $package->slug,
            'country' => $data['country'],
        ]);

        $this->madeName = $tenant->name;
        $this->madeAddress = 'https://'.$tenant->slug.config('tenancy.subdomain_suffix');

        $this->reset(['name', 'slug', 'ownerName', 'ownerEmail', 'ownerPassword']);
    }

    public function render()
    {
        return view('livewire.super.store-form', [
            'countries' => config('countries'),
            'currencies' => config('currencies'),
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'suggestion' => Str::slug($this->name),
        ]);
    }
}
