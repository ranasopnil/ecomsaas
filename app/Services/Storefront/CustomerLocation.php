<?php

namespace App\Services\Storefront;

use App\Support\GeoPoint;
use Illuminate\Http\Request;

/**
 * Where the customer says they are.
 *
 * Remembered per visit, not per account: most shoppers never sign in, and the
 * question "can you deliver to me?" has to be answerable before that.
 */
class CustomerLocation
{
    protected const KEY = 'shopper.location';

    public function __construct(protected Request $request) {}

    public function point(): ?GeoPoint
    {
        $saved = $this->request->session()->get(self::KEY);

        if (! is_array($saved)) {
            return null;
        }

        return GeoPoint::tryFrom($saved['latitude'] ?? null, $saved['longitude'] ?? null);
    }

    public function label(): ?string
    {
        $saved = $this->request->session()->get(self::KEY);

        return is_array($saved) ? ($saved['label'] ?? null) : null;
    }

    public function isSet(): bool
    {
        return $this->point() !== null;
    }

    public function remember(GeoPoint $point, ?string $label = null): void
    {
        $this->request->session()->put(self::KEY, [
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'label' => $label !== null ? mb_substr(trim($label), 0, 120) : null,
        ]);
    }

    public function forget(): void
    {
        $this->request->session()->forget(self::KEY);
    }
}
