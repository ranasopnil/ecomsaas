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

    /** A real order, waiting for the shopkeeper to look at it. */
    public const STATUS_PLACED = 'placed';

    /** The shopkeeper has taken it on. */
    public const STATUS_APPROVED = 'approved';

    /** Being picked and packed. */
    public const STATUS_PROCESSING = 'processing';

    /** Given to a courier, on its way. */
    public const STATUS_HANDED_OVER = 'handed_over';

    /** It arrived. */
    public const STATUS_DELIVERED = 'delivered';

    /** It came back, and the shopkeeper said why. */
    public const STATUS_NOT_DELIVERED = 'not_delivered';

    /** Called off. Stock has been given back. */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The road an order travels, in order, for the rail a shopkeeper reads.
     * Anything that ends an order early is not on it.
     *
     * @var array<int, string>
     */
    public const JOURNEY = [
        self::STATUS_PLACED,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
        self::STATUS_HANDED_OVER,
        self::STATUS_DELIVERED,
    ];

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    /** Cash on delivery: the courier collects it. */
    public const PAYMENT_ON_DELIVERY = 'on_delivery';

    protected $fillable = [
        'tenant_id', 'reference', 'view_token',
        'customer_name', 'customer_phone', 'customer_address', 'customer_note',
        'delivery_area_id', 'delivery_area_name', 'latitude', 'longitude',
        'payment_gateway', 'payment_id',
        'courier_id', 'courier_name', 'tracking_code',
        'goods_minor', 'delivery_minor', 'total_minor', 'currency', 'currency_exponent',
        'status', 'payment_status',
        'placed_at', 'paid_at', 'approved_at', 'handed_over_at', 'delivered_at',
        'cancelled_at', 'cancelled_reason', 'not_delivered_reason',
        'cod_received_minor', 'cod_received_at',
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
            'approved_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cod_received_minor' => 'integer',
            'cod_received_at' => 'datetime',
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

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /**
     * Every step this order has taken, oldest first.
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
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

    /**
     * Where the parcel has got to, said plainly.
     */
    public function statusLabel(): string
    {
        return self::labelFor($this->status);
    }

    /**
     * One status, in words. In one place so a customer and a shopkeeper are
     * never told two different things about the same order.
     */
    public static function labelFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_PAYMENT => 'Waiting for payment',
            self::STATUS_PLACED => 'New order',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PROCESSING => 'Being packed',
            self::STATUS_HANDED_OVER => 'With the courier',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_NOT_DELIVERED => 'Not delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Placed',
        };
    }

    /**
     * How the money stands, which is a different question from where the
     * parcel is: an order can be delivered and still owed for.
     */
    public function paymentLabel(): string
    {
        return match (true) {
            $this->isWaitingToBePaid() => 'Waiting for payment',
            $this->payment_status === self::PAYMENT_ON_DELIVERY => 'Cash on delivery',
            $this->isPaid() => 'Paid',
            default => 'Not paid',
        };
    }

    /**
     * A real order: one the shop has taken money for or promised to deliver,
     * as opposed to an attempt that never became one.
     */
    public function isReal(): bool
    {
        return ! in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CANCELLED], true);
    }

    /**
     * Still needs the shopkeeper to do something.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_PLACED, self::STATUS_APPROVED, self::STATUS_PROCESSING,
            self::STATUS_HANDED_OVER, self::STATUS_NOT_DELIVERED,
        ], true);
    }

    /**
     * How far along the journey this order is, for the rail on the screen.
     * Anything off the road — cancelled, not delivered — is not on it.
     */
    public function journeyStep(): ?int
    {
        $at = array_search($this->status, self::JOURNEY, true);

        return $at === false ? null : $at;
    }

    /**
     * Where the courier's own page shows this parcel, if there is one.
     */
    public function trackingUrl(): ?string
    {
        return $this->courier?->trackingUrlFor($this->tracking_code);
    }
}
