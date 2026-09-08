<?php

namespace App\Services\Orders;

use App\Exceptions\OrderStepRefused;
use App\Models\Courier;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Accounts\Ledger;
use App\Services\Catalogue\InventoryService;
use App\Services\Couriers\CourierFactory;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Moving an order along, one step at a time.
 *
 * A shopkeeper approves an order, packs it, hands it to a courier, and says
 * what happened when it arrived. Every step is written down with who took it
 * and why, and the history is never edited: a mistake is corrected by taking
 * another step, not by rubbing out the last one.
 *
 * Nothing here changes what an order is worth. Turning an order down puts its
 * stock back on the shelf; it does not give money back — a refund is its own
 * deliberate act, on the payment, and is never done by a screen about parcels.
 */
class OrderFlow
{
    /**
     * What may follow what. Anything not listed here cannot happen, so an
     * order can never skip from new straight to delivered.
     *
     * @var array<string, array<int, string>>
     */
    public const NEXT = [
        Order::STATUS_PENDING_PAYMENT => [],
        Order::STATUS_PLACED => [Order::STATUS_APPROVED, Order::STATUS_CANCELLED],
        Order::STATUS_APPROVED => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_HANDED_OVER, Order::STATUS_CANCELLED],
        Order::STATUS_HANDED_OVER => [Order::STATUS_DELIVERED, Order::STATUS_NOT_DELIVERED],
        // A parcel that came back can go out again, or be given up on.
        Order::STATUS_NOT_DELIVERED => [Order::STATUS_HANDED_OVER, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function __construct(
        protected InventoryService $stock,
        protected Ledger $ledger,
        protected CourierFactory $couriers,
    ) {}

    /**
     * The steps this order can take next, ready for buttons.
     *
     * @return array<int, array{status: string, label: string, needs: ?string, tone: string}>
     */
    public function steps(Order $order): array
    {
        return array_map(fn (string $to) => [
            'status' => $to,
            'label' => $this->actionLabel($to, $order->status),
            'needs' => $this->needs($to),
            'tone' => in_array($to, [Order::STATUS_CANCELLED, Order::STATUS_NOT_DELIVERED], true) ? 'bad' : 'ok',
        ], self::NEXT[$order->status] ?? []);
    }

    public function can(Order $order, string $to): bool
    {
        return in_array($to, self::NEXT[$order->status] ?? [], true);
    }

    /**
     * What the shopkeeper has to fill in before a step can be taken.
     */
    public function needs(string $to): ?string
    {
        return match ($to) {
            Order::STATUS_HANDED_OVER => 'courier',
            Order::STATUS_CANCELLED, Order::STATUS_NOT_DELIVERED => 'reason',
            default => null,
        };
    }

    /**
     * Take one step.
     *
     * @param  array{reason?: ?string, courier_id?: int|string|null, tracking_code?: ?string}  $with
     *
     * @throws OrderStepRefused
     */
    public function apply(Order $order, string $to, array $with = [], ?User $by = null): Order
    {
        if (! $this->can($order, $to)) {
            throw OrderStepRefused::notNext($order->status, $to);
        }

        $reason = trim((string) ($with['reason'] ?? ''));
        $trackingCode = trim((string) ($with['tracking_code'] ?? ''));
        $courier = null;

        if ($this->needs($to) === 'reason' && $reason === '') {
            throw OrderStepRefused::needsReason();
        }

        if ($this->needs($to) === 'courier') {
            // Only this shop's own couriers. An id from anywhere else finds
            // nothing, because the model is scoped to the bound shop.
            $courier = Courier::find((int) ($with['courier_id'] ?? 0));

            if ($courier === null) {
                throw OrderStepRefused::needsCourier();
            }

            // A courier the shop has switched to automatic books the parcel
            // itself, and the number comes back from the courier rather than
            // being typed. Done before anything is written down: if the
            // courier will not take it, the order has not moved.
            if ($courier->isReady()) {
                $booked = $this->couriers->for($courier)->book($order, $with);

                $trackingCode = $booked['tracking_code'] ?? $booked['consignment_id'];
            }
        }

        $by ??= auth()->user();

        $moved = DB::transaction(function () use ($order, $to, $reason, $trackingCode, $courier, $by) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            // Two people on the same order at the same time: the second one
            // is told rather than quietly overwriting the first.
            if ($fresh === null || $fresh->status !== $order->status) {
                throw OrderStepRefused::movedAlready();
            }

            $from = $fresh->status;

            $fresh->forceFill([
                'status' => $to,
                ...$this->stamps($to, $reason),
                ...($courier === null ? [] : [
                    'courier_id' => $courier->id,
                    // Kept beside the id: a courier may be renamed or removed
                    // later, and this order still says who took the parcel.
                    'courier_name' => $courier->name,
                    'tracking_code' => $trackingCode !== '' ? $trackingCode : null,
                ]),
            ])->save();

            OrderEvent::create([
                'tenant_id' => $fresh->tenant_id,
                'order_id' => $fresh->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $reason !== '' ? mb_substr($reason, 0, 1000) : null,
                'courier_id' => $courier?->id,
                'courier_name' => $courier?->name,
                'tracking_code' => $trackingCode !== '' ? $trackingCode : null,
                'user_id' => $by?->id,
                'user_name' => $by?->name,
            ]);

            // Rule seven: anything that has to happen because of this — a
            // message to the customer — is written with it, not left to a
            // queue that could lose it.
            OutboxEvent::create([
                'tenant_id' => $fresh->tenant_id,
                'type' => 'order.'.$to,
                'payload' => [
                    'order_id' => $fresh->id,
                    'reference' => $fresh->reference,
                    'from' => $from,
                    'to' => $to,
                ],
                'available_at' => now(),
            ]);

            return $fresh;
        });

        // Outside the transaction that marks it: putting stock back is its own
        // set of atomic statements, and each is safe on its own.
        if ($to === Order::STATUS_CANCELLED) {
            $this->putStockBack($moved);
        }

        return $moved->refresh();
    }

    /**
     * Cash the courier collected on delivery, handed over to the shop.
     *
     * This is not a step along the road — the parcel has already arrived. It
     * is the money catching up, and it is entered in the shop's book at the
     * same moment as it is written on the order, so the two can never
     * disagree.
     *
     * The courier may hand over less than was due, or in more than one lot.
     * Both are recorded as they happened rather than tidied up: the order
     * says what is still owed until it is all in.
     *
     * @throws OrderStepRefused
     */
    public function cashFromCourier(Order $order, Money $amount, ?string $note = null, ?User $by = null): Order
    {
        if ($order->payment_status !== Order::PAYMENT_ON_DELIVERY) {
            throw OrderStepRefused::notCashOnDelivery();
        }

        if ($order->status !== Order::STATUS_DELIVERED) {
            throw OrderStepRefused::notDeliveredYet();
        }

        if ($amount->minor <= 0) {
            throw OrderStepRefused::noAmount();
        }

        $stillOwed = $order->total_minor - $order->cod_received_minor;

        if ($amount->minor > $stillOwed) {
            throw OrderStepRefused::moreThanOwed();
        }

        $by ??= auth()->user();

        $moved = DB::transaction(function () use ($order, $amount, $note, $by) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->cod_received_minor !== $order->cod_received_minor) {
                throw OrderStepRefused::movedAlready();
            }

            $received = $fresh->cod_received_minor + $amount->minor;
            $settled = $received >= $fresh->total_minor;

            $fresh->forceFill([
                'cod_received_minor' => $received,
                'cod_received_at' => now(),
                // Only once every taka of it is in is the order paid for.
                ...($settled ? [
                    'payment_status' => Order::PAYMENT_PAID,
                    'paid_at' => $fresh->paid_at ?? now(),
                ] : []),
            ])->save();

            OrderEvent::create([
                'tenant_id' => $fresh->tenant_id,
                'order_id' => $fresh->id,
                'from_status' => $fresh->status,
                'to_status' => OrderEvent::CASH_RECEIVED,
                'note' => trim(($amount->toDisplay().' '.$amount->currency)
                    .($settled ? '' : ', still owed '.$fresh->total->minus(new Money($received, $amount->currency, $amount->exponent))->toDisplay())
                    .($note ? ' — '.$note : '')),
                'courier_id' => $fresh->courier_id,
                'courier_name' => $fresh->courier_name,
                'user_id' => $by?->id,
                'user_name' => $by?->name,
            ]);

            // The shop's book, written in the same breath as the order.
            $this->ledger->record($amount, [
                'direction' => LedgerEntry::IN,
                'kind' => LedgerEntry::KIND_COD,
                'description' => 'Cash from '.($fresh->courier_name ?: 'the courier')
                    .' for '.$fresh->reference
                    .($note ? ' — '.$note : ''),
                'order_id' => $fresh->id,
            ], $by);

            OutboxEvent::create([
                'tenant_id' => $fresh->tenant_id,
                'type' => 'order.cash_received',
                'payload' => [
                    'order_id' => $fresh->id,
                    'reference' => $fresh->reference,
                    'amount_minor' => $amount->minor,
                    'settled' => $settled,
                ],
                'available_at' => now(),
            ]);

            return $fresh;
        });

        return $moved->refresh();
    }

    /**
     * Is the shop still waiting on the courier for this one?
     */
    public function isWaitingForCash(Order $order): bool
    {
        return $order->status === Order::STATUS_DELIVERED
            && $order->payment_status === Order::PAYMENT_ON_DELIVERY
            && $order->cod_received_minor < $order->total_minor;
    }

    /**
     * The times and reasons that go with a step.
     *
     * @return array<string, mixed>
     */
    protected function stamps(string $to, string $reason): array
    {
        return match ($to) {
            Order::STATUS_APPROVED => ['approved_at' => now()],
            Order::STATUS_HANDED_OVER => ['handed_over_at' => now(), 'not_delivered_reason' => null],
            Order::STATUS_DELIVERED => ['delivered_at' => now(), 'not_delivered_reason' => null],
            Order::STATUS_NOT_DELIVERED => ['not_delivered_reason' => mb_substr($reason, 0, 250)],
            Order::STATUS_CANCELLED => ['cancelled_at' => now(), 'cancelled_reason' => mb_substr($reason, 0, 250)],
            default => [],
        };
    }

    /**
     * Everything this order was holding goes back on the shelf.
     */
    protected function putStockBack(Order $order): void
    {
        foreach ($order->lines()->with('variant')->get() as $line) {
            if ($line->product_variant_id !== null && $line->variant) {
                $this->stock->release($line->variant, $line->quantity, $order->reference);
            }
        }
    }

    /**
     * What the button says. Written as the thing the shopkeeper is doing.
     */
    protected function actionLabel(string $to, string $from): string
    {
        return match ($to) {
            Order::STATUS_APPROVED => 'Approve',
            Order::STATUS_PROCESSING => 'Start packing',
            Order::STATUS_HANDED_OVER => $from === Order::STATUS_NOT_DELIVERED
                ? 'Send it out again'
                : 'Hand to courier',
            Order::STATUS_DELIVERED => 'Mark delivered',
            Order::STATUS_NOT_DELIVERED => 'Not delivered',
            // Turning down a new order reads differently from stopping one
            // the shop has already taken on.
            Order::STATUS_CANCELLED => $from === Order::STATUS_PLACED ? 'Reject' : 'Cancel order',
            default => Order::labelFor($to),
        };
    }
}
