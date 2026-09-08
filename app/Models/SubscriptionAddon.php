<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One extra a shop has bought, at the price agreed when it bought it.
 *
 * The price is a snapshot for the same reason a subscription's is: raising
 * what an add-on costs must never change what a shop already agreed to pay.
 */
class SubscriptionAddon extends Model
{
    use BelongsToTenant;

    /** Asked for, but the money has not been found yet. Grants nothing. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'addon_id', 'quantity',
        'price_minor', 'currency', 'currency_exponent',
        'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency,currency_exponent',
            'price_minor' => 'integer',
            'currency_exponent' => 'integer',
            'quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function scopeInForce(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * What this costs the shop each period, for however many they bought.
     */
    public function total(): Money
    {
        return $this->price->times($this->quantity);
    }
}
