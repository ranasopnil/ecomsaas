<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Demo\SampleShop;
use Illuminate\Console\Command;

class SeedSampleShop extends Command
{
    protected $signature = 'shop:demo
                            {store : The shop address label, e.g. dhaka-fashion}
                            {--remove : Take the sample data back out again}
                            {--no-photos : Skip making the pictures}';

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

        $result = $sample->fill($tenant, withPhotos: ! $this->option('no-photos'));

        $this->info("Added {$result['products']} products to {$tenant->name}.");
        $this->line("{$result['variants']} sellable lines and {$result['movements']} stock movements over the last fortnight.");
        $this->line('Remove it all later with: php artisan shop:demo '.$tenant->slug.' --remove');

        return self::SUCCESS;
    }
}
