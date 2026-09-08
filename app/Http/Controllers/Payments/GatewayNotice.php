<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Services\Orders\SettleOrder;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What a gateway tells the shop directly, server to server, with no customer
 * in the middle.
 *
 * This is the message that matters. A customer who pays and then closes the
 * tab never comes back to the return address, but the gateway still tells us
 * here, so the order is finished either way.
 *
 * Gateways resend these on purpose. Writing the message down under a unique
 * key before acting on it is what makes a resend harmless.
 */
abstract class GatewayNotice extends Controller
{
    public function __construct(
        protected PaymentProcessor $processor,
        protected SettleOrder $orders,
    ) {}

    /**
     * Act on one notice, exactly once, whatever the gateway does afterwards.
     *
     * @param  array<string, mixed>  $payload  what to keep for the record
     */
    protected function actOnce(
        Payment $payment,
        PaymentMethod $method,
        string $eventId,
        string $type,
        array $payload,
        string $outcome,
    ): Response {
        // Written down before it is acted on. A resend finds the row already
        // there and stops, so the money is never taken twice.
        //
        // In its own transaction so that being second is a savepoint rolled
        // back, not the whole request's transaction left unusable, which is
        // what Postgres does with a failed statement.
        try {
            $seen = DB::transaction(fn () => PaymentEvent::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'event_id' => $eventId,
                'type' => $type,
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response('Already handled.', 200);
        }

        try {
            $payment = $this->processor->settle($payment, $method, $outcome);

            // Nothing settles the order behind us, so it is settled here.
            // Both of these are safe to run after the customer's own return
            // address has already run them.
            $order = $payment->order_id === null ? null : Order::find($payment->order_id);

            if ($order !== null) {
                $payment->isPaid()
                    ? $this->orders->paid($order, $payment)
                    : $this->orders->cancelled($order, $payment->failure_reason ?: 'The payment did not go through.');
            }
        } catch (Throwable $e) {
            // Let the gateway try again: without this the note above would
            // say we had handled a message we in fact dropped half way.
            $seen->delete();

            throw $e;
        }

        return response('OK', 200);
    }
}
