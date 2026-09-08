<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\LedgerEntry;
use App\Services\Accounts\Ledger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The shop's own book of money in and money out.
 *
 * Lines the shop wrote for itself — a payment taken, cash handed over by a
 * courier — sit beside anything the shopkeeper enters by hand. Nothing on
 * this screen edits a line: a mistake is put right by writing its opposite,
 * and both stay in the book.
 */
#[Layout('layouts.admin')]
#[Title('Accounts')]
class LedgerIndex extends Component
{
    use WithPagination;

    #[Url(as: 'when')]
    public string $period = 'this_month';

    #[Url(as: 'show')]
    public string $only = 'all';

    /** The "write something down" form. */
    public bool $adding = false;

    public string $direction = LedgerEntry::OUT;

    public string $amount = '';

    public string $description = '';

    public string $occurred_on = '';

    /** The line being put right, and why. */
    public ?int $reversing = null;

    public string $why = '';

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedOnly(): void
    {
        $this->resetPage();
    }

    public function add(): void
    {
        $this->reset(['amount', 'description', 'reversing', 'why']);
        $this->resetErrorBag();

        $this->adding = true;
        $this->direction = LedgerEntry::OUT;
        $this->occurred_on = $this->today()->toDateString();
    }

    public function cancel(): void
    {
        $this->reset(['adding', 'amount', 'description', 'reversing', 'why']);
        $this->resetErrorBag();
    }

    public function save(Ledger $ledger): void
    {
        $this->validate([
            'direction' => ['required', 'in:in,out'],
            'description' => ['required', 'string', 'max:200'],
            'occurred_on' => ['required', 'date', 'before_or_equal:'.$this->today()->addDay()->toDateString()],
        ], [
            'description.required' => 'Say what this was for.',
            'occurred_on.before_or_equal' => 'A day that has not happened yet cannot have money in it.',
        ]);

        $shop = Tenancy::current();

        try {
            $money = Money::fromDecimal(
                trim($this->amount) === '' ? '0' : trim($this->amount),
                $shop->currency,
                (int) $shop->currency_exponent,
            );
        } catch (\InvalidArgumentException) {
            $this->addError('amount', 'Write the amount in figures, like 500 or 500.50.');

            return;
        }

        if ($money->minor <= 0) {
            $this->addError('amount', 'An amount of nothing is not worth writing down.');

            return;
        }

        $entry = $ledger->record($money, [
            'direction' => $this->direction,
            'kind' => $this->direction === LedgerEntry::IN
                ? LedgerEntry::KIND_OTHER_IN
                : LedgerEntry::KIND_EXPENSE,
            'description' => trim($this->description),
            'occurred_on' => $this->occurred_on,
        ]);

        $this->cancel();
        $this->resetPage();

        $this->dispatch('toast', [
            'text' => $entry === null ? 'Nothing was written.' : 'Written in your book.',
            'tone' => $entry === null ? 'bad' : 'ok',
        ]);
    }

    /**
     * Start putting a line right. The original is never touched.
     */
    public function startReversing(int $entryId): void
    {
        $this->reset(['adding', 'amount', 'description']);
        $this->resetErrorBag();

        $this->reversing = $entryId;
        $this->why = '';
    }

    public function reverse(Ledger $ledger): void
    {
        $entry = LedgerEntry::find($this->reversing);

        if ($entry === null) {
            $this->cancel();

            return;
        }

        $correction = $ledger->reverse($entry, $this->why);

        $this->cancel();

        $this->dispatch('toast', [
            'text' => $correction === null
                ? 'That line has already been put right.'
                : 'The opposite line was written. Both stay in the book.',
            'tone' => $correction === null ? 'bad' : 'ok',
        ]);
    }

    /**
     * @return array{from: ?Carbon, to: ?Carbon}
     */
    protected function window(): array
    {
        $today = $this->today();

        return match ($this->period) {
            'this_month' => ['from' => $today->copy()->startOfMonth(), 'to' => $today],
            'last_month' => [
                'from' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                'to' => $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => ['from' => $today->copy()->startOfYear(), 'to' => $today],
            default => ['from' => null, 'to' => null],
        };
    }

    protected function today(): Carbon
    {
        return Carbon::now(Tenancy::current()?->timezone ?: config('app.timezone'));
    }

    public function render(Ledger $ledger)
    {
        $window = $this->window();

        $entries = LedgerEntry::query()
            ->when($window['from'], fn (Builder $q) => $q->whereDate('occurred_on', '>=', $window['from']->toDateString()))
            ->when($window['to'], fn (Builder $q) => $q->whereDate('occurred_on', '<=', $window['to']->toDateString()))
            ->when($this->only !== 'all', fn (Builder $q) => $q->where('direction', $this->only))
            ->with('order')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.admin.ledger-index', [
            'entries' => $entries,
            'totals' => $ledger->totals($window['from'], $window['to']),
            'balance' => $ledger->balance(),
            'owed' => $ledger->owedByCouriers(),
            'reversed' => LedgerEntry::query()
                ->whereNotNull('reverses_id')
                ->pluck('reverses_id')
                ->all(),
        ]);
    }
}
