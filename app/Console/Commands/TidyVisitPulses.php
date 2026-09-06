<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Tenant;
use App\Models\VisitPulse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Throws away the day-old fingerprints behind the "right now" figure.
 *
 * Every shop is tidied inside its own context, so this never reaches across
 * from one shop to another.
 */
class TidyVisitPulses extends Command
{
    protected $signature = 'visits:tidy';

    protected $description = 'Delete visitor fingerprints that are older than a day';

    public function handle(): int
    {
        $before = Carbon::now('UTC')->subHours(VisitPulse::KEEP_HOURS);
        $deleted = 0;

        Tenant::query()->each(function (Tenant $store) use ($before, &$deleted) {
            $deleted += Tenancy::run($store, fn () => VisitPulse::query()
                ->where('seen_at', '<', $before)
                ->delete());
        });

        $this->info("Removed {$deleted} old visitor fingerprint(s).");

        return self::SUCCESS;
    }
}
