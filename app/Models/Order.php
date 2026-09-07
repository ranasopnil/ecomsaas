<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One order, as agreed at one moment.
 *
 * Nothing here is rewritten once the order is placed. A refund or a
 * cancellation is recorded alongside it, never by editing the totals: a
 * customer and a shopkeeper must always be able to see what was actually
 * agreed.
 */
class Order extends Model
{
    use BelongsToTenant, HasFactory;

    /** Placed, but the customer is still at the payment page. */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    /** A real order the shop should act on. */
    public const STATUS_PLACED = 'placed';

    /** Called off. Stock has been given back. */
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    /** Cash on delivery: the courier collects it. */
    public const PAYMENT_ON_DELIVERY = 'on_delivery';

    protected $fillable = [
        'tenant_id', 'reference', 'view_token',
        'customer_name', 'customer_phone', 'customer_address', 'customer_note',
        'delivery_area_id', 'delivery_area_name', 'latitude', 'longitude',
        'payment_gateway', 'payment_id',
        'goods_minor', 'delivery_minor', 'total_minor', 'currency', 'currency_exponent',
        'status', 'payment_status',
        'placed_at', 'paid_at', 'cancelled_at', 'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'goods' => MoneyCast::class.':goods_minor,currency,currency_exponent',
            'delivery' => MoneyCast::class.':delivery_minor,currency,currency_exponent',
            'total' => MoneyCast::class.':total_minor,currency,currency_exponent',
            'goods_minor' => 'integer',
            'delivery_minor' => 'integer',
            'total_minor' => 'integer',
            'currency_exponent' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class, 'delivery_area_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * What the customer quotes on the phone. Short, and without the letters
     * that get misheard.
     */
    public static function newReference(): string
    {
        return 'O'.now()->format('ymd').Str::upper(Str::random(6));
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isWaitingToBePaid(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /** How many things, counting each one. */
    public function itemCount(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    /** Said plainly, for a customer or a shopkeeper. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelled',
            $this->isWaitingToBePaid() => 'Waiting for payment',
            $this->payment_status === self::PAYMENT_ON_DELIVERY => 'Placed — pay on delivery',
            $this->isPaid() => 'Placed and paid',
            default => 'Placed',
        };
    }
}
