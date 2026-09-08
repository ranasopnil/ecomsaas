<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a shop can buy on top of its plan. Owned by the platform, not by
 * any shop, exactly as a plan is.
 *
 * Two kinds, and no more: more of something counted, or one feature switched
 * on. Both are answered in the one place that already knows what a shop is
 * allowed to do, so nothing else in the platform has to learn what an add-on
 * is.
 */
class Addon extends Model
{
    /** More of a counted thing — 50 more products at a time. */
    public const KIND_UNITS = 'units';

    /** One feature turned on, however the plan had it. */
    public const KIND_SWITCH = 'switch';

    protected $fillable = [
        'name', 'slug', 'description', 'kind', 'feature', 'unit_amount', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AddonPrice::class);
    }

    /**
     * What this costs in one currency, or null if it is not sold there.
     */
    public function priceIn(string $currency): ?Money
    {
        $price = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('currency', $currency)
            : $this->prices()->where('currency', $currency)->first();

        return $price?->price;
    }

    public function isUnits(): bool
    {
        return $this->kind === self::KIND_UNITS;
    }

    /**
     * "50 more products", "Discount codes switched on".
     */
    public function what(): string
    {
        $label = config('features.'.$this->feature.'.label', $this->feature);

        return $this->isUnits()
            ? number_format((int) $this->unit_amount).' more '.mb_strtolower($label)
            : $label.' switched on';
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
