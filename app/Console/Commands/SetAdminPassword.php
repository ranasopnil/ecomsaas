<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SetAdminPassword extends Command
{
    protected $signature = 'admin:password {email}';

    protected $description = 'Change a platform staff password';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error("No staff account for {$this->argument('email')}.");

            return self::FAILURE;
        }

        $password = $this->secret('New password (at least 12 characters)');

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $admin->update(['password' => $password]);

        $this->info("Password changed for {$admin->email}.");

        return self::SUCCESS;
    }
}
