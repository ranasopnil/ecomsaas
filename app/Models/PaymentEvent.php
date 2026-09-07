<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a gateway told us, written down once.
 *
 * Gateways resend messages — that is normal, not a fault. The unique key on
 * (tenant, gateway, event id) is what stops a resend being acted on twice.
 */
class PaymentEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'payment_id', 'gateway', 'event_id', 'type', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
