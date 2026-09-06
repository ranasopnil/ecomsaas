<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The plans merchants can buy. Prices are starting figures in Bangladeshi Taka
 * and are meant to be changed by the platform owner.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For a new shop finding its first customers.',
                'price_minor' => 99000,
                'trial_days' => 14,
                'sort_order' => 1,
                'entitlements' => [
                    'products' => 50,
                    'staff_accounts' => 1,
                    'custom_domains' => 0,
                    'storage_mb' => 500,
                    'orders_per_month' => 200,
                    'cash_on_delivery' => true,
                    'online_payments' => false,
                    'courier_pickup' => false,
                    'discount_codes' => false,
                ],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For a shop selling every day.',
                'price_minor' => 249000,
                'trial_days' => 14,
                'sort_order' => 2,
                'entitlements' => [
                    'products' => 1000,
                    'staff_accounts' => 3,
                    'custom_domains' => 1,
                    'storage_mb' => 5000,
                    'orders_per_month' => 2000,
                    'cash_on_delivery' => true,
                    'online_payments' => true,
                    'courier_pickup' => true,
                    'discount_codes' => true,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For an established shop with a team.',
                'price_minor' => 599000,
                'trial_days' => 14,
                'sort_order' => 3,
                'entitlements' => [
                    'products' => null,
                    'staff_accounts' => 10,
                    'custom_domains' => 3,
                    'storage_mb' => 25000,
                    'orders_per_month' => null,
                    'cash_on_delivery' => true,
                    'online_payments' => true,
                    'courier_pickup' => true,
                    'discount_codes' => true,
                ],
            ],
        ];

        foreach ($packages as $definition) {
            $entitlements = $definition['entitlements'];
            unset($definition['entitlements']);

            $package = Package::updateOrCreate(
                ['slug' => $definition['slug']],
                $definition + [
                    'currency' => 'BDT',
                    'currency_exponent' => 2,
                    'billing_period' => Package::PERIOD_MONTHLY,
                    'is_public' => true,
                    'is_active' => true,
                ],
            );

            foreach ($entitlements as $feature => $value) {
                $package->entitlements()->updateOrCreate(
                    ['feature' => $feature],
                    is_bool($value)
                        ? ['enabled' => $value, 'limit_value' => null]
                        : ['enabled' => true, 'limit_value' => $value],
                );
            }
        }
    }
}
