<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price_minor' => 99000,
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'billing_period' => Package::PERIOD_MONTHLY,
            'trial_days' => 0,
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    /**
     * Every plan needs a price somewhere, or no shop can be put on it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Package $package) {
            if ($package->prices()->count() === 0) {
                $package->prices()->create([
                    'currency' => $package->currency,
                    'currency_exponent' => $package->currency_exponent,
                    'price_minor' => $package->price_minor,
                    'billing_period' => $package->billing_period,
                ]);
            }
        });
    }

    /**
     * @param  array<string, int|bool|null>  $entitlements  feature => ceiling, or feature => on/off
     */
    public function allowing(array $entitlements): static
    {
        return $this->afterCreating(function (Package $package) use ($entitlements) {
            foreach ($entitlements as $feature => $value) {
                $package->entitlements()->updateOrCreate(
                    ['feature' => $feature],
                    is_bool($value)
                        ? ['enabled' => $value, 'limit_value' => null]
                        : ['enabled' => true, 'limit_value' => $value],
                );
            }
        });
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn () => ['trial_days' => $days]);
    }
}
