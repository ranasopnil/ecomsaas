<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Facades\Tenancy;
use App\Support\GeoPoint;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One place a shop delivers to, with a name the shopkeeper chose.
 *
 * A shop draws these once — "Dhaka city", "Mirpur", "Uttara" — and then picks
 * from the list on every product, instead of drawing a map each time.
 */
class DeliveryArea extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'latitude', 'longitude', 'radius_km', 'position',
        'delivery_charge_minor',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'position' => 'integer',
            'delivery_charge_minor' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('tenant_id');
    }

    /** What the shop charges to reach here. Zero is free. */
    public function deliveryCharge(): Money
    {
        $shop = $this->relationLoaded('tenant') ? $this->tenant : Tenancy::current();

        return new Money(
            (int) ($this->delivery_charge_minor ?? 0),
            $shop?->currency ?? 'BDT',
            (int) ($shop?->currency_exponent ?? 2),
        );
    }

    public function point(): ?GeoPoint
    {
        return GeoPoint::tryFrom($this->latitude, $this->longitude);
    }

    public function reaches(?GeoPoint $customer): bool
    {
        $centre = $this->point();

        if ($centre === null || ! ($this->radius_km > 0)) {
            return false;
        }

        return $customer !== null && $customer->isWithin($this->radius_km, $centre);
    }

    /**
     * "Mirpur, within 5 km" — how the area reads on a shopkeeper's screen.
     */
    public function describe(): string
    {
        return $this->name.', within '.$this->distance().' km';
    }

    public function distance(): string
    {
        return rtrim(rtrim(number_format((float) $this->radius_km, 1), '0'), '.');
    }
}
