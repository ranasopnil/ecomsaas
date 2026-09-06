<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'hostname' => fake()->unique()->domainWord().config('tenancy.subdomain_suffix'),
            'type' => Domain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => Domain::STATUS_VERIFIED,
            'verified_at' => now(),
        ];
    }

    public function custom(string $hostname): static
    {
        return $this->state(fn () => [
            'hostname' => $hostname,
            'type' => Domain::TYPE_CUSTOM,
            'status' => Domain::STATUS_PENDING,
            'verified_at' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => Domain::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
