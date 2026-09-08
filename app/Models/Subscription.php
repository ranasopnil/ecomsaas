<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Exceptions\ImmutableFinancialRecord;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One store's subscription to one package.
 *
 * The money on this row is a snapshot of what was agreed. It is never edited
 * afterwards: a plan change ends this row and starts a new one.
 */
class Subscription extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id',
        'package_id',
        'scheduled_package_id',
        'scheduled_change_at',
        'status',
        'price_minor',
        'currency',
        'currency_exponent',
        'billing_period',
        'starts_at',
        'trial_ends_at',
        'current_period_ends_at',
        'grace_ends_at',
        'locked_at',
        'cancelled_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency,currency_exponent',
            'price_minor' => 'integer',
            'currency_exponent' => 'integer',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'locked_at' => 'datetime',
            'scheduled_change_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The plan this shop is dropping to at its renewal date. A downgrade is
     * booked rather than applied, so the shop keeps what it paid for.
     */
    public function scheduledPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'scheduled_package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function hasScheduledChange(): bool
    {
        return $this->scheduled_package_id !== null;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * The shop's dashboard is closed until it pays. Its storefront is never
     * touched: its customers had no part in this.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Overdue, but still inside the days of grace.
     */
    public function isInGrace(): bool
    {
        return $this->isPastDue() && ! $this->isLocked();
    }

    /**
     * Whole days until the renewal date, or until the dashboard closes once
     * it has passed. Never negative.
     */
    public function daysLeft(): int
    {
        $until = $this->isPastDue() ? $this->grace_ends_at : $this->current_period_ends_at;

        return $until === null ? 0 : max(0, (int) now()->startOfDay()->diffInDays($until->copy()->startOfDay(), false));
    }

    /**
     * Still entitles the store to use the platform.
     */
    public function isActive(): bool
    {
        if (! in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE, self::STATUS_PAST_DUE], true)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE, self::STATUS_PAST_DUE])
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Money already agreed must not be rewritten. Ending a subscription and
     * starting a new one is how a plan change is recorded.
     */
    protected static function booted(): void
    {
        static::updating(function (self $subscription): void {
            foreach (['price_minor', 'currency', 'currency_exponent', 'package_id', 'starts_at'] as $locked) {
                if ($subscription->isDirty($locked)) {
                    throw new ImmutableFinancialRecord(
                        "Subscription [{$subscription->id}] is already agreed; [{$locked}] cannot change. "
                        .'End this subscription and start a new one instead.'
                    );
                }
            }
        });
    }
}
