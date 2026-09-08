<?php

namespace App\Console\Commands;

use App\Services\Billing\Renewals;
use Illuminate\Console\Command;

/**
 * Moves every shop's subscription to where it should be today.
 *
 * Trials end, months run out, days of grace expire. This is what notices.
 * It never charges anybody — the money moves by hand, and staff confirm it.
 */
class TickSubscriptions extends Command
{
    protected $signature = 'subscriptions:tick';

    protected $description = 'Move subscriptions past their renewal dates and close the dashboards of shops that have not paid';

    public function handle(Renewals $renewals): int
    {
        $tally = $renewals->tick();

        $this->info(sprintf(
            'Checked %d; %d fell due, %d dashboards closed, %d plan changes applied, %d reminded.',
            $tally['checked'], $tally['past_due'], $tally['locked'], $tally['changed'], $tally['reminded'],
        ));

        return self::SUCCESS;
    }
}
