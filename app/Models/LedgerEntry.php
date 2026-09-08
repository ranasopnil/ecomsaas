<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in the shop's book of money.
 *
 * Written once and never changed. A line that was wrong is put right by
 * writing its opposite beside it, pointing back at it — the shop's history
 * has to say what actually happened.
 */
class LedgerEntry extends Model
{
    use BelongsToTenant;

    public const IN = 'in';

    public const OUT = 'out';

    /** A customer paid through a gateway. */
    public const KIND_PAYMENT = 'payment_received';

    /** A courier handed over cash it collected on delivery. */
    public const KIND_COD = 'cod_collected';

    /** Money given back to a customer. */
    public const KIND_REFUND = 'refund_paid';

    /** Anything else the shop took in. */
    public const KIND_OTHER_IN = 'other_income';

    /** Anything the shop spent. */
    public const KIND_EXPENSE = 'expense';

    /** A line written to undo an earlier one. */
    public const KIND_CORRECTION = 'correction';

    protected $fillable = [
        'tenant_id', 'occurred_on', 'direction', 'kind',
        'amount_minor', 'currency', 'currency_exponent',
        'description', 'order_id', 'payment_id', 'source_key',
        'reverses_id', 'user_id', 'user_name',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency,currency_exponent',
            'amount_minor' => 'integer',
            'currency_exponent' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The line this one undoes, if it is a correction.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function scopeMoneyIn(Builder $query): Builder
    {
        return $query->where('direction', self::IN);
    }

    public function scopeMoneyOut(Builder $query): Builder
    {
        return $query->where('direction', self::OUT);
    }

    public function isMoneyIn(): bool
    {
        return $this->direction === self::IN;
    }

    /**
     * The amount with its sign, for adding a column up.
     */
    public function signedMinor(): int
    {
        return $this->isMoneyIn() ? $this->amount_minor : -$this->amount_minor;
    }

    /**
     * Said plainly, for the shopkeeper's own screen.
     */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_PAYMENT => 'Paid online',
            self::KIND_COD => 'Cash from courier',
            self::KIND_REFUND => 'Refund',
            self::KIND_OTHER_IN => 'Other money in',
            self::KIND_EXPENSE => 'Money spent',
            self::KIND_CORRECTION => 'Correction',
            default => 'Other',
        };
    }

    /**
     * A line the shopkeeper wrote themselves, rather than one the shop wrote
     * because money moved. Only these can be corrected by hand.
     */
    public function wasWrittenByHand(): bool
    {
        return in_array($this->kind, [self::KIND_OTHER_IN, self::KIND_EXPENSE, self::KIND_COD], true);
    }

    /**
     * Zero in this shop's own money, for starting a total.
     */
    public static function nothing(Order|Tenant|null $from = null): Money
    {
        $currency = $from?->currency ?? 'BDT';
        $exponent = $from?->currency_exponent ?? 2;

        return Money::zero($currency, (int) $exponent);
    }
}
