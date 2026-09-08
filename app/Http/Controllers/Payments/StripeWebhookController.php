<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\Stripe;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What Stripe tells the shop directly.
 *
 * Anyone can post to this address, so nothing is believed until the signature
 * made with the shop's own signing secret checks out.
 */
class StripeWebhookController extends GatewayNotice
{
    /** What Stripe calls the things we act on. Everything else is ignored. */
    protected const MEANING = [
        'checkout.session.completed' => 'success',
        'checkout.session.async_payment_succeeded' => 'success',
        'checkout.session.expired' => 'failure',
        'checkout.session.async_payment_failed' => 'failure',
    ];

    public function __invoke(Request $request): Response
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
            // Not something we act on. Answering 200 stops Stripe retrying a
            // message we were never going to do anything with.
            return response('Nothing to do.', 200);
        }

        $payment = Payment::where('gateway', Stripe::KEY)->where('gateway_payment_id', $sessionId)->first();

        if ($payment === null) {
            return response('No such payment here.', 200);
        }

        return $this->actOnce(
            $payment,
            $method,
            $eventId,
            $type,
            ['session_id' => $sessionId, 'payment_status' => $session['payment_status'] ?? null],
            self::MEANING[$type],
        );
    }
}
