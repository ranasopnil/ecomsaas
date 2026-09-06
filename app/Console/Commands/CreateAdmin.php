<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {name?} {email?}';

    protected $description = 'Create a platform staff account for the super admin panel';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Full name');
        $email = $this->argument('email') ?: $this->ask('Email address');
        $password = $this->secret('Password (at least 12 characters)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:admins,email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Admin::create(['name' => $name, 'email' => $email, 'password' => $password]);

        $this->info("Staff account created for {$email}.");
        $this->line('Sign in at '.rtrim(config('app.url'), '/').'/super/login');

        return self::SUCCESS;
    }
}
