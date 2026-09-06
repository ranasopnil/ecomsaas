<?php

namespace App\Console\Commands;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateStoreUser extends Command
{
    protected $signature = 'store:user
                            {store : The shop address label, e.g. dhaka-fashion}
                            {name : The person\'s name}
                            {email : Their email address}
                            {--owner : Make them the shop owner}';

    protected $description = 'Add someone to a shop so they can sign in to its admin';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('store'))->first();

        if ($tenant === null) {
            $this->error("No shop with the address label [{$this->argument('store')}].");

            return self::FAILURE;
        }

        $password = $this->secret('Password (at least 12 characters)');

        return Tenancy::run($tenant, function () use ($tenant, $password) {
            $validator = Validator::make([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required', 'email',
                    Rule::unique('users', 'email')->where('tenant_id', $tenant->id),
                ],
                'password' => ['required', 'string', 'min:12'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            // The shop's plan decides how many people can have an account.
            Entitlements::ensureCanAdd('staff_accounts', User::count());

            User::create([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => $password,
                'role' => $this->option('owner') ? User::ROLE_OWNER : User::ROLE_STAFF,
                'is_active' => true,
            ]);

            $this->info("{$this->argument('email')} can now sign in to {$tenant->name}.");
            $this->line('Sign in at https://'.$tenant->slug.config('tenancy.subdomain_suffix').'/admin/login');

            return self::SUCCESS;
        });
    }
}
