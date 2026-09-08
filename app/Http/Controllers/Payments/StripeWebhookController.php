<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Services\Orders\SettleOrder;
use App\Services\Payments\Gateways\Stripe;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What Stripe tells the shop directly, server to server.
 *
 * This is the message that matters. A customer who pays and then closes the
 * tab never comes back to the return address, but Stripe still tells us here,
 * so the order is finished either way.
 *
 * Anyone can post to this address, so nothing is believed until the signature
 * made with the shop's own signing secret checks out. Stripe resends messages
 * on purpose; the unique key on the event id is what makes a resend harmless.
 */
class StripeWebhookController extends Controller
{
    /** What Stripe calls the things we act on. Everything else is noted and ignored. */
    protected const MEANING = [
        'checkout.session.completed' => 'success',
        'checkout.session.async_payment_succeeded' => 'success',
        'checkout.session.expired' => 'failure',
        'checkout.session.async_payment_failed' => 'failure',
    ];

    public function __invoke(Request $request, PaymentProcessor $processor, SettleOrder $orders): Response
    {
        // The shop is already known: this address is the shop's own.
        $method = PaymentMethod::where('gateway', Stripe::KEY)->first();
        $secret = (string) ($method?->credentials['webhook_secret'] ?? '');

        if ($method === null || $secret === '') {
            return response('This shop is not taking Stripe webhooks.', 404);
        }

        $payload = $request->getContent();

        if (! Stripe::signatureIsValid($payload, (string) $request->header('Stripe-Signature', ''), $secret)) {
            // 400 rather than 200: Stripe should know it was refused, and a
            // forged message must never look accepted.
            return response('Signature did not check out.', 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Unreadable.', 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $session = $event['data']['object'] ?? [];
        $sessionId = is_array($session) ? (string) ($session['id'] ?? '') : '';

        if ($eventId === '' || ! isset(self::MEANING[$type]) || $sessionId === '') {
            // Not something we act on. Answering 200 stops Stripe retrying
            // a message we were never going to do anything with.
            return response('Nothing to do.', 200);
        }

        $payment = Payment::where('gateway', Stripe::KEY)->where('gateway_payment_id', $sessionId)->first();

        if ($payment === null) {
            return response('No such payment here.', 200);
        }

        // Write the message down before acting on it. A resend finds the row
        // already there and stops, so the money is never taken twice.
        //
        // Wrapped in its own transaction so the refusal is a savepoint being
        // rolled back rather than the whole request's transaction being left
        // unusable, which is what Postgres does with a failed statement.
        try {
            $seen = DB::transaction(fn () => PaymentEvent::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'gateway' => Stripe::KEY,
                'event_id' => $eventId,
                'type' => $type,
                'payload' => ['session_id' => $sessionId, 'payment_status' => $session['payment_status'] ?? null],
            ]));
        } catch (UniqueConstraintViolationException) {
            return response('Already handled.', 200);
        }

        try {
            $payment = $processor->settle($payment, $method, self::MEANING[$type]);

            // Nothing settles the order behind us, so it is settled here.
            // Both of these are safe to run after the customer's own return
            // address has already run them.
            $order = $payment->order_id === null ? null : Order::find($payment->order_id);

            if ($order !== null) {
                $payment->isPaid()
                    ? $orders->paid($order, $payment)
                    : $orders->cancelled($order, $payment->failure_reason ?: 'The payment did not go through.');
            }
        } catch (Throwable $e) {
            // Let Stripe try again: without this the note above would say we
            // had handled a message we in fact dropped half way through.
            $seen->delete();

            throw $e;
        }

        return response('OK', 200);
    }
}
