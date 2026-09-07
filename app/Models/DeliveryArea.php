<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\GeoPoint;
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

    protected $fillable = ['tenant_id', 'name', 'latitude', 'longitude', 'radius_km', 'position'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'position' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('tenant_id');
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
