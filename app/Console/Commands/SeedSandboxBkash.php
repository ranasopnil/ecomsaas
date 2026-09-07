<?php

namespace App\Console\Commands;

use App\Exceptions\GatewayFailed;
use App\Facades\Tenancy;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\Gateways\Bkash;
use Illuminate\Console\Command;

/**
 * Fills a shop's bKash card in with bKash's own practice details, so payments
 * can be tried on the dev server without real money.
 *
 * The details come from the environment, never from this file, and the command
 * refuses to run anywhere but a development server.
 */
class SeedSandboxBkash extends Command
{
    protected $signature = 'payments:sandbox-bkash
                            {--shop= : The slug of one shop, instead of every Bangladeshi shop}
                            {--test : Ask bKash whether the details work once they are saved}';

    protected $description = 'Fill shops in with bKash practice details so payments can be tried on the dev server';

    public function handle(GatewayFactory $factory): int
    {
        if (app()->environment('production')) {
            $this->error('This is for development servers only.');

            return self::FAILURE;
        }

        $credentials = array_filter(config('services.bkash_sandbox', []));

        if (count($credentials) < 4) {
            $this->error('The bKash practice details are not in the environment.');
            $this->line('Fill in BKASH_SANDBOX_APP_KEY, BKASH_SANDBOX_APP_SECRET,');
            $this->line('BKASH_SANDBOX_USERNAME and BKASH_SANDBOX_PASSWORD, then run this again.');

            return self::FAILURE;
        }

        // Reading across every shop is a platform job, so no store is bound yet.
        $shops = Tenant::query()
            ->when($this->option('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->when(! $this->option('shop'), fn ($query) => $query->where('country_code', 'BD'))
            ->get();

        if ($shops->isEmpty()) {
            $this->warn('No shop matched. bKash is for Bangladeshi shops.');

            return self::FAILURE;
        }

        foreach ($shops as $shop) {
            Tenancy::run($shop, fn () => $this->fillIn($shop, $credentials, $factory));
        }

        $this->newLine();
        $this->info('Done. These are practice details: bKash will accept payments but no money moves.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $credentials
     */
    protected function fillIn(Tenant $shop, array $credentials, GatewayFactory $factory): void
    {
        $method = PaymentMethod::firstOrNew(['gateway' => Bkash::KEY]);

        $method->tenant_id = $shop->id;
        $method->settings = [
            ...($method->settings ?? []),
            'username' => $credentials['username'],
            'sandbox' => true,
        ];

        // Rewritten outright rather than merged, so an old key cannot survive.
        $method->credentials = [
            'app_key' => $credentials['app_key'],
            'app_secret' => $credentials['app_secret'],
            'password' => $credentials['password'],
        ];

        $method->is_enabled = true;
        $method->save();

        $this->line("<info>{$shop->name}</info> — bKash filled in and switched on (practice system).");

        if (! $this->option('test')) {
            return;
        }

        try {
            $factory->for($method)->testConnection();
            $this->line('  bKash accepted the details.');
        } catch (GatewayFailed $e) {
            $this->line('  <error>bKash refused them:</error> '.$e->getMessage());
        }
    }
}
