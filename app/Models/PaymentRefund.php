<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money handed back to a customer. Always a new row — a payment that has
 * happened is never rewritten.
 */
class PaymentRefund extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'payment_id', 'amount_minor', 'currency', 'currency_exponent',
        'status', 'gateway_refund_id', 'reason', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency,currency_exponent',
            'meta' => 'array',
            'amount_minor' => 'integer',
            'currency_exponent' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
