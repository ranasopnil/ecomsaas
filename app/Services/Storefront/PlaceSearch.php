<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turning a typed place name into a point on the map.
 *
 * Done on our side rather than in the browser, so the free service sees one
 * well-behaved caller instead of every shopkeeper's laptop, and so the same
 * search is not asked twice.
 */
class PlaceSearch
{
    public function __construct(protected MapProviders $maps) {}

    /**
     * @return array<int, array{name: string, latitude: float, longitude: float}>
     */
    public function find(string $query, string $provider, ?string $country = null): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return [];
        }

        $key = 'places:'.$provider.':'.strtolower($country ?? '').':'.md5(mb_strtolower($query));

        return Cache::remember($key, now()->addDay(), fn () => $provider === 'google'
            ? $this->fromGoogle($query, $country)
            : $this->fromOpenStreetMap($query, $country));
    }

    /**
     * The other way round: a point on the map turned back into a place name,
     * so a customer whose browser told us where they are sees "Gulshan,
     * Dhaka" rather than a pair of numbers.
     *
     * Rounded to about ten metres before caching, because two shoppers on the
     * same street do not need two lookups.
     */
    public function describe(float $latitude, float $longitude, string $provider, ?string $country = null): ?string
    {
        $key = 'place-at:'.$provider.':'.round($latitude, 4).','.round($longitude, 4);

        return Cache::remember($key, now()->addDays(7), fn () => $provider === 'google'
            ? $this->nameFromGoogle($latitude, $longitude)
            : $this->nameFromOpenStreetMap($latitude, $longitude));
    }

    protected function nameFromOpenStreetMap(float $latitude, float $longitude): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => config('app.name').' ('.config('app.url').')',
        ])
            ->acceptJson()
            ->timeout(10)
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'jsonv2',
                'zoom' => 16,
                'addressdetails' => 0,
            ]);

        if ($response->failed()) {
            return null;
        }

        $name = trim((string) $response->json('display_name', ''));

        return $name !== '' ? $name : null;
    }

    protected function nameFromGoogle(float $latitude, float $longitude): ?string
    {
        $key = $this->maps->key('google');

        if ($key === null) {
            return $this->nameFromOpenStreetMap($latitude, $longitude);
        }

        $response = Http::acceptJson()->timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $latitude.','.$longitude,
            'key' => $key,
        ]);

        if ($response->failed() || $response->json('status') !== 'OK') {
            return null;
        }

        $name = trim((string) $response->json('results.0.formatted_address', ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @return array<int, array{name: string, latitude: float, longitude: float}>
     */
    protected function fromOpenStreetMap(string $query, ?string $country): array
    {
        $response = Http::withHeaders([
            // Nominatim asks callers to say who they are. Being anonymous is
            // how a free service ends up blocking you.
            'User-Agent' => config('app.name').' ('.config('app.url').')',
        ])
            ->acceptJson()
            ->timeout(10)
            ->get('https://nominatim.openstreetmap.org/search', array_filter([
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 6,
                'addressdetails' => 0,
                'countrycodes' => $country ? strtolower($country) : null,
            ]));

        if ($response->failed()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $row) => [
                'name' => (string) ($row['display_name'] ?? ''),
                'latitude' => (float) ($row['lat'] ?? 0),
                'longitude' => (float) ($row['lon'] ?? 0),
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, latitude: float, longitude: float}>
     */
    protected function fromGoogle(string $query, ?string $country): array
    {
        $key = $this->maps->key('google');

        if ($key === null) {
            return $this->fromOpenStreetMap($query, $country);
        }

        $response = Http::acceptJson()->timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', array_filter([
            'address' => $query,
            'key' => $key,
            'components' => $country ? 'country:'.$country : null,
        ]));

        if ($response->failed() || ($response->json('status') !== 'OK')) {
            return [];
        }

        return collect($response->json('results', []))
            ->take(6)
            ->map(fn (array $row) => [
                'name' => (string) ($row['formatted_address'] ?? ''),
                'latitude' => (float) data_get($row, 'geometry.location.lat', 0),
                'longitude' => (float) data_get($row, 'geometry.location.lng', 0),
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()
            ->all();
    }
}
