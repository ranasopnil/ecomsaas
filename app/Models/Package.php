<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A plan a merchant can subscribe to. Owned by the platform, not by any store.
 */
class Package extends Model
{
    use HasFactory, SoftDeletes;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_minor',
        'currency',
        'currency_exponent',
        'billing_period',
        'trial_days',
        'is_public',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency,currency_exponent',
            'price_minor' => 'integer',
            'currency_exponent' => 'integer',
            'trial_days' => 'integer',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PackageEntitlement::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order');
    }
}
