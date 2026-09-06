<?php

namespace App\Livewire\Admin;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Services\Domains\DomainChecker;
use App\Services\Domains\DomainRegistrar;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Web address')]
class DomainIndex extends Component
{
    public string $hostname = '';

    /** The row that just changed, so only that one is highlighted. */
    public ?int $justChanged = null;

    public function add(): void
    {
        try {
            $domain = app(DomainRegistrar::class)->add(Tenancy::current(), $this->hostname);
        } catch (InvalidArgumentException|LimitReached $e) {
            $this->addError('hostname', $e->getMessage());

            return;
        }

        $this->hostname = '';
        $this->justChanged = $domain->id;
        $this->resetErrorBag();

        $this->dispatch('toast', [
            'text' => $domain->hostname.' added. Now point it at us with the record below.',
            'tone' => 'ok',
        ]);
    }

    /**
     * Look right now, rather than waiting for the next scheduled check.
     */
    public function checkNow(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        $pointsHere = app(DomainChecker::class)->check($domain);

        $this->justChanged = $domainId;

        $this->dispatch('toast', [
            'text' => $pointsHere
                ? $domain->hostname.' is pointing here. Your shop will answer on it within a minute or two.'
                : $domain->fresh()->last_check_result,
            'tone' => $pointsHere ? 'ok' : 'bad',
        ]);
    }

    public function makePrimary(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        try {
            app(DomainRegistrar::class)->makePrimary($domain);
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);

            return;
        }

        $this->justChanged = $domainId;
        $this->dispatch('toast', ['text' => $domain->hostname.' is now your shop\'s main address.', 'tone' => 'ok']);
    }

    public function remove(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        try {
            app(DomainRegistrar::class)->remove($domain);
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);

            return;
        }

        $this->dispatch('toast', ['text' => $domain->hostname.' was removed.', 'tone' => 'ok']);
    }

    public function render()
    {
        $domains = Domain::orderByDesc('is_primary')->orderBy('type')->orderBy('id')->get();
        $owned = $domains->where('type', Domain::TYPE_CUSTOM)->filter(fn ($d) => ! str_starts_with($d->hostname, 'www.'));

        return view('livewire.admin.domain-index', [
            'domains' => $domains,
            'serverIp' => config('tenancy.server_ips')[0] ?? '',
            'allowance' => Entitlements::limit('custom_domains'),
            'used' => $owned->count(),
            'canAddMore' => Entitlements::limit('custom_domains') === null
                || $owned->count() < Entitlements::limit('custom_domains'),
        ]);
    }
}
