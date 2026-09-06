<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.fake()->unique()->numberBetween(1, 999999))->value(),
            'status' => Tenant::STATUS_ACTIVE,
            'email' => fake()->unique()->safeEmail(),
            'country_code' => 'BD',
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'timezone' => 'Asia/Dhaka',
            'prices_include_tax' => false,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_SUSPENDED]);
    }
}
