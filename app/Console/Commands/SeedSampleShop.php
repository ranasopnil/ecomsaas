<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Demo\SampleShop;
use Illuminate\Console\Command;

class SeedSampleShop extends Command
{
    protected $signature = 'shop:demo
                            {store : The shop address label, e.g. dhaka-fashion}
                            {--kind=grocery : Which sort of shop to build: grocery or fashion}
                            {--remove : Take the sample data back out again}
                            {--no-photos : Skip the pictures}';

    protected $description = 'Fill a shop with sample products so the screens can be seen with something in them';

    public function handle(SampleShop $sample): int
    {
        $tenant = Tenant::where('slug', $this->argument('store'))->first();

        if ($tenant === null) {
            $this->error("No shop with the address label [{$this->argument('store')}].");

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $result = $sample->remove($tenant);

            $this->info("Removed {$result['products']} sample product(s) from {$tenant->name}.");
            $this->line('Anything you added yourself was left alone.');

            return self::SUCCESS;
        }

        $kind = (string) $this->option('kind');

        if (! in_array($kind, SampleShop::kinds(), true)) {
            $this->error("[{$kind}] is not a kind of demo shop. Try: ".implode(', ', SampleShop::kinds()).'.');

            return self::FAILURE;
        }

        $result = $sample->fill($tenant, withPhotos: ! $this->option('no-photos'), kind: $kind);

        $this->info("Added {$result['products']} {$kind} products to {$tenant->name}.");
        $this->line("{$result['variants']} sellable lines and {$result['movements']} stock movements over the last fortnight.");

        if (! $this->option('no-photos')) {
            $this->line('Photographs come from Wikimedia Commons under free licences.');
            $this->line('They are demo data only; the licences are in database/demo/photo-credits.md.');
        }

        $this->line('Remove it all later with: php artisan shop:demo '.$tenant->slug.' --remove');

        return self::SUCCESS;
    }
}
