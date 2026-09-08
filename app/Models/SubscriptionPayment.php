<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a shop says it paid the platform, and what staff made of it.
 *
 * The money itself moves by bKash, bank transfer or hand. This is the record
 * either side of that: the shop writing down what it sent, and a member of
 * staff saying whether it arrived. Neither is ever edited afterwards — a
 * mistake is a new row, not a rewritten one.
 */
class SubscriptionPayment extends Model
{
    use BelongsToTenant;

    /** The shop says it has paid. Nobody has checked yet. */
    public const STATUS_CLAIMED = 'claimed';

    /** Staff found the money. The plan moves on. */
    public const STATUS_CONFIRMED = 'confirmed';

    /** Staff could not find it. */
    public const STATUS_REJECTED = 'rejected';

    public const PURPOSE_RENEWAL = 'renewal';

    public const PURPOSE_UPGRADE = 'upgrade';

    public const PURPOSE_ADDON = 'addon';

    /** How shops actually pay, here. */
    public const METHODS = [
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'rocket' => 'Rocket',
        'bank' => 'Bank transfer',
        'cash' => 'Cash',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'tenant_id', 'subscription_id', 'amount_minor', 'currency', 'currency_exponent',
        'purpose', 'method', 'reference', 'note', 'meta', 'status',
        'claimed_at', 'confirmed_at', 'confirmed_by', 'decision_note',
        'covers_from', 'covers_to',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency,currency_exponent',
            'amount_minor' => 'integer',
            'meta' => 'array',
            'currency_exponent' => 'integer',
            'claimed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'covers_from' => 'datetime',
            'covers_to' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLAIMED);
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_CLAIMED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? 'Not said';
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            self::PURPOSE_UPGRADE => 'Moving to a bigger plan',
            self::PURPOSE_ADDON => 'An extra on top of the plan',
            default => 'Plan renewal',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'Received',
            self::STATUS_REJECTED => 'Not found',
            default => 'Waiting to be checked',
        };
    }
}
