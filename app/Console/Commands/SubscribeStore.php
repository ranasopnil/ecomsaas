<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Console\Command;

class SubscribeStore extends Command
{
    protected $signature = 'store:subscribe
                            {store : The store address label, e.g. dhaka-fashion}
                            {package : The plan, e.g. growth}';

    protected $description = 'Put a store on a plan';

    public function handle(SubscribeToPackage $subscribe): int
    {
        $tenant = Tenant::where('slug', $this->argument('store'))->first();

        if ($tenant === null) {
            $this->error("No store with the address label [{$this->argument('store')}].");

            return self::FAILURE;
        }

        $package = Package::where('slug', $this->argument('package'))->first();

        if ($package === null) {
            $this->error("No plan called [{$this->argument('package')}].");

            return self::FAILURE;
        }

        $subscription = $subscribe->handle($tenant, $package);

        $this->info("[{$tenant->name}] is now on the {$package->name} plan ({$subscription->status}).");

        return self::SUCCESS;
    }
}
