<?php

namespace App\Services\Accounts;

use App\Facades\Tenancy;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The shop's book of money in and money out.
 *
 * Lines are written, never edited. Anything the shop is told about twice — a
 * gateway repeating itself, a page reloaded — is entered once, because each
 * line that comes from something names that thing and no two lines may name
 * the same one.
 *
 * A line that was wrong is put right by writing its opposite beside it. The
 * book then shows both, which is the point: it is a record of what happened,
 * not a picture of how things ought to have gone.
 */
class Ledger
{
    /**
     * Write one line.
     *
     * @param  array{direction: string, kind: string, description: string, occurred_on?: mixed, order_id?: ?int, payment_id?: ?int, source_key?: ?string, reverses_id?: ?int}  $about
     * @return LedgerEntry|null the line, or null when this thing was already in the book
     */
    public function record(Money $amount, array $about, ?User $by = null): ?LedgerEntry
    {
        if ($amount->minor <= 0) {
            // A line for nothing is not a record of anything.
            return null;
        }

        $by ??= auth()->user();

        // Only ever the shop's own people. Platform staff confirming a
        // payment are recorded in the audit log, not written into the shop's
        // book as though they worked there.
        if (! $by instanceof User) {
            $by = null;
        }

        try {
            // In its own transaction so that being second is a savepoint
            // rolled back, not the whole request's transaction left unusable,
            // which is what Postgres does with a failed statement.
            return DB::transaction(fn () => LedgerEntry::create([
                'tenant_id' => Tenancy::id(),
                'occurred_on' => $about['occurred_on'] ?? Carbon::now($this->timezone())->toDateString(),
                'direction' => $about['direction'],
                'kind' => $about['kind'],
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'currency_exponent' => $amount->exponent,
                'description' => mb_substr($about['description'], 0, 250),
                'order_id' => $about['order_id'] ?? null,
                'payment_id' => $about['payment_id'] ?? null,
                'source_key' => $about['source_key'] ?? null,
                'reverses_id' => $about['reverses_id'] ?? null,
                'user_id' => $by?->id,
                'user_name' => $by?->name,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Already banked. Saying so is the right answer, not an error.
            return null;
        }
    }

    /**
     * What the book adds up to.
     */
    public function balance(): Money
    {
        $in = (int) LedgerEntry::query()->moneyIn()->sum('amount_minor');
        $out = (int) LedgerEntry::query()->moneyOut()->sum('amount_minor');

        return $this->money($in - $out);
    }

    /**
     * Money in, money out and the difference, between two days.
     *
     * @return array{in: Money, out: Money, net: Money}
     */
    public function totals(?Carbon $from = null, ?Carbon $to = null): array
    {
        $between = fn ($query) => $query
            ->when($from, fn ($q) => $q->whereDate('occurred_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('occurred_on', '<=', $to->toDateString()));

        $in = (int) $between(LedgerEntry::query()->moneyIn())->sum('amount_minor');
        $out = (int) $between(LedgerEntry::query()->moneyOut())->sum('amount_minor');

        return [
            'in' => $this->money($in),
            'out' => $this->money($out),
            'net' => $this->money($in - $out),
        ];
    }

    /**
     * Cash the couriers have collected on the shop's behalf and not yet
     * handed over. Counted from the orders themselves, one row each, so it
     * cannot drift from what the orders say.
     */
    public function owedByCouriers(): Money
    {
        $owed = (int) Order::query()
            ->where('status', Order::STATUS_DELIVERED)
            ->where('payment_status', Order::PAYMENT_ON_DELIVERY)
            ->selectRaw('coalesce(sum(total_minor - cod_received_minor), 0) as owed')
            ->value('owed');

        return $this->money(max(0, $owed));
    }

    /**
     * Put a line right by writing its opposite.
     *
     * The original stays exactly as it was. Anyone reading the book later
     * sees both the mistake and the correction, which is what a book of
     * money is for.
     */
    public function reverse(LedgerEntry $entry, string $why, ?User $by = null): ?LedgerEntry
    {
        if ($entry->reverses_id !== null || $entry->kind === LedgerEntry::KIND_CORRECTION) {
            return null;
        }

        // Only once: a line already put right is not put right again.
        if (LedgerEntry::where('reverses_id', $entry->id)->exists()) {
            return null;
        }

        return $this->record($entry->amount, [
            'direction' => $entry->isMoneyIn() ? LedgerEntry::OUT : LedgerEntry::IN,
            'kind' => LedgerEntry::KIND_CORRECTION,
            'description' => mb_substr(trim($why) !== '' ? $why : 'Correction', 0, 200)
                .' (undoes: '.$entry->description.')',
            'order_id' => $entry->order_id,
            'payment_id' => $entry->payment_id,
            'reverses_id' => $entry->id,
        ], $by);
    }

    protected function money(int $minor): Money
    {
        $shop = Tenancy::current();

        return new Money($minor, $shop?->currency ?? 'BDT', (int) ($shop?->currency_exponent ?? 2));
    }

    protected function timezone(): string
    {
        return Tenancy::current()?->timezone ?: config('app.timezone');
    }
}
