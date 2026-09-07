<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One attempt to take money from one customer through one gateway.
 *
 * Once it is completed its amount never changes. Money given back is a
 * PaymentRefund row pointing here, so the history stays readable.
 */
class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    /** Created, nothing sent to the gateway yet. */
    public const STATUS_PENDING = 'pending';

    /** The gateway has a payment open and the customer is on their page. */
    public const STATUS_INITIATED = 'initiated';

    /** Money taken. Final. */
    public const STATUS_COMPLETED = 'completed';

    /** The gateway refused it, or it ran out of time. Final. */
    public const STATUS_FAILED = 'failed';

    /** The customer backed out. Final. */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'gateway', 'reference', 'order_id', 'amount_minor', 'currency',
        'currency_exponent', 'status', 'gateway_payment_id', 'gateway_transaction_id',
        'payer_reference', 'payer_account', 'failure_reason', 'is_sandbox', 'meta',
        'initiated_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency,currency_exponent',
            'meta' => 'array',
            'is_sandbox' => 'boolean',
            'amount_minor' => 'integer',
            'currency_exponent' => 'integer',
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    /**
     * Our own id for this attempt. It is what the shopkeeper and the gateway
     * both quote, so it has to be unique and hard to mistype.
     */
    public static function newReference(): string
    {
        return 'P'.now()->format('ymd').Str::upper(Str::random(8));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * What has already been given back.
     */
    public function refundedAmount(): Money
    {
        $minor = (int) $this->refunds()
            ->where('status', PaymentRefund::STATUS_COMPLETED)
            ->sum('amount_minor');

        return new Money($minor, $this->currency, $this->currency_exponent);
    }

    /**
     * What is still refundable.
     */
    public function refundableAmount(): Money
    {
        return $this->amount->minus($this->refundedAmount());
    }
}
