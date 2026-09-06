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
                'prices' => [
                    'BDT' => 99000,
                    'MYR' => 3900,
                    'IDR' => 149000,
                    'AED' => 3900,
                    'SAR' => 3900,
                    'USD' => 1000,
                ],
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
                'prices' => [
                    'BDT' => 249000,
                    'MYR' => 9900,
                    'IDR' => 399000,
                    'AED' => 9900,
                    'SAR' => 9900,
                    'USD' => 2500,
                ],
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
                'prices' => [
                    'BDT' => 599000,
                    'MYR' => 23900,
                    'IDR' => 949000,
                    'AED' => 23900,
                    'SAR' => 23900,
                    'USD' => 5900,
                ],
                'entitlements' => [
                    'products' => null,
                    'staff_accounts' => 10,
                    'custom_domains' => 2,
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
            $prices = $definition['prices'];
            unset($definition['entitlements'], $definition['prices']);

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

            foreach ($prices as $currency => $minor) {
                $package->prices()->updateOrCreate(
                    ['currency' => $currency],
                    [
                        'price_minor' => $minor,
                        'currency_exponent' => config("currencies.{$currency}.exponent"),
                        'billing_period' => $package->billing_period,
                    ],
                );
            }

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
