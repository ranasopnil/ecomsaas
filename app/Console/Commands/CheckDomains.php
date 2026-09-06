<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Concerns\TenantScope;
use App\Models\Domain;
use App\Services\Domains\DomainChecker;
use Illuminate\Console\Command;

/**
 * Looks at every domain still waiting, to see whether it has started pointing
 * at us yet. Runs on a schedule; merchants can also press a button to check
 * one straight away.
 */
class CheckDomains extends Command
{
    protected $signature = 'domains:check {--hostname= : Check just this one}';

    protected $description = 'See whether merchant domains have been pointed at this server yet';

    public function handle(DomainChecker $checker): int
    {
        // Reading across every shop is a platform job, so the tenant scope is
        // dropped here on purpose.
        $domains = Domain::withoutGlobalScope(TenantScope::class)
            ->with('tenant')
            ->where('type', Domain::TYPE_CUSTOM)
            ->when($this->option('hostname'), fn ($query, $hostname) => $query->where('hostname', $hostname))
            ->when(! $this->option('hostname'), fn ($query) => $query->where('status', '!=', Domain::STATUS_VERIFIED))
            ->get();

        if ($domains->isEmpty()) {
            $this->info('No domains are waiting to be checked.');

            return self::SUCCESS;
        }

        $verified = 0;

        foreach ($domains as $domain) {
            if ($domain->tenant === null) {
                continue;
            }

            $pointsHere = Tenancy::run($domain->tenant, fn () => $checker->check($domain));

            $this->line(sprintf(
                '%-40s %s',
                $domain->hostname,
                $pointsHere ? 'verified' : $domain->last_check_result,
            ));

            $verified += $pointsHere ? 1 : 0;
        }

        $this->info("Checked {$domains->count()} domain(s), {$verified} pointing here.");

        return self::SUCCESS;
    }
}
