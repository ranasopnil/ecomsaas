<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A courier one shop hands parcels to.
 *
 * Each shop keeps its own list, in its own words. Nothing is shared between
 * shops: a courier one merchant uses is none of another merchant's business.
 */
class Courier extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'phone', 'tracking_url', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Where this courier's own page shows a parcel, or null when the shop has
     * not given one.
     *
     * Built by putting the consignment number in place of {code}. Nothing the
     * shopkeeper typed is treated as anything but text.
     */
    public function trackingUrlFor(?string $code): ?string
    {
        $template = trim((string) $this->tracking_url);
        $code = trim((string) $code);

        if ($template === '' || $code === '' || ! preg_match('~^https?://~i', $template)) {
            return null;
        }

        return str_contains($template, '{code}')
            ? str_replace('{code}', rawurlencode($code), $template)
            : $template;
    }

    /**
     * The couriers this shop can hand a parcel to today.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function inUse()
    {
        return static::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get();
    }
}
