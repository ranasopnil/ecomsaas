<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Services\Catalogue\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * What happens to an order once the money is decided.
 *
 * Both of these are safe to run twice. A gateway may tell us the same thing
 * more than once, a customer may reload the page they came back to, and
 * neither must take stock twice or send two confirmations.
 */
class SettleOrder
{
    public function __construct(protected InventoryService $stock) {}

    /**
     * The money arrived. The order becomes a real order.
     */
    public function paid(Order $order, ?Payment $payment = null): Order
    {
        return DB::transaction(function () use ($order, $payment) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->isPaid() || $fresh->isCancelled()) {
                return $fresh ?? $order;
            }

            $fresh->forceFill([
                'status' => Order::STATUS_PLACED,
                'payment_status' => Order::PAYMENT_PAID,
                'payment_id' => $payment?->id ?? $fresh->payment_id,
                'placed_at' => $fresh->placed_at ?? now(),
                'paid_at' => now(),
            ])->save();

            OutboxEvent::create([
                'tenant_id' => $fresh->tenant_id,
                'type' => 'order.placed',
                'payload' => ['order_id' => $fresh->id, 'reference' => $fresh->reference],
                'available_at' => now(),
            ]);

            return $fresh;
        });
    }

    /**
     * The money did not arrive, or the customer backed out. Everything the
     * order was holding goes back on the shelf.
     */
    public function cancelled(Order $order, string $reason): Order
    {
        $cancelled = DB::transaction(function () use ($order, $reason) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->isCancelled() || $fresh->isPaid()) {
                return null;
            }

            $fresh->forceFill([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_reason' => mb_substr($reason, 0, 250),
            ])->save();

            return $fresh;
        });

        if ($cancelled === null) {
            return $order->refresh();
        }

        // Outside the transaction that marks it: putting stock back is its own
        // set of atomic statements, and each is safe on its own.
        foreach ($cancelled->lines()->with('variant')->get() as $line) {
            if ($line->product_variant_id !== null && $line->variant) {
                $this->stock->release($line->variant, $line->quantity, $cancelled->reference);
            }
        }

        return $cancelled->refresh();
    }
}
