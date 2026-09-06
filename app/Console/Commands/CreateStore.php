<?php

namespace App\Console\Commands;

use App\Services\Stores\ProvisionStore;
use Illuminate\Console\Command;

class CreateStore extends Command
{
    protected $signature = 'store:create
                            {name : The shop name, e.g. "Dhaka Fashion"}
                            {slug? : The address label, e.g. dhaka-fashion}';

    protected $description = 'Create a store and give it its free address';

    public function handle(ProvisionStore $provisioner): int
    {
        $tenant = $provisioner->handle($this->argument('name'), $this->argument('slug'));

        $this->info("Store [{$tenant->name}] created.");
        $this->line('Open: https://'.$tenant->slug.config('tenancy.subdomain_suffix'));

        return self::SUCCESS;
    }
}
