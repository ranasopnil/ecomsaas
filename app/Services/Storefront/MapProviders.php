<?php

namespace App\Services\Storefront;

use App\Models\Tenant;

/**
 * Which map a shop draws its delivery area on.
 *
 * This is the platform's decision rather than the shop's, because one of the
 * two bills the platform for every map opened. A shop with no choice recorded
 * gets the platform default.
 */
class MapProviders
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('maps.providers', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $provider): ?array
    {
        return $this->all()[$provider] ?? null;
    }

    /**
     * A provider needing a key cannot be handed out until the key is there.
     */
    public function isUsable(string $provider): bool
    {
        $definition = $this->find($provider);

        if ($definition === null) {
            return false;
        }

        return ! ($definition['needs_key'] ?? false) || $this->key($provider) !== null;
    }

    public function key(string $provider): ?string
    {
        return $provider === 'google'
            ? (config('services.google_maps.key') ?: null)
            : null;
    }

    public function default(): string
    {
        $default = (string) config('maps.default', 'osm');

        return $this->isUsable($default) ? $default : 'osm';
    }

    public function forShop(Tenant $shop): string
    {
        return $shop->map_provider !== null && $this->isUsable($shop->map_provider)
            ? $shop->map_provider
            : $this->default();
    }

    public function nameFor(Tenant $shop): string
    {
        return $this->find($this->forShop($shop))['name'] ?? 'Map';
    }

    public function setForShop(Tenant $shop, ?string $provider): bool
    {
        if ($provider !== null && ! $this->isUsable($provider)) {
            return false;
        }

        $shop->forceFill(['map_provider' => $provider])->save();

        return true;
    }
}
