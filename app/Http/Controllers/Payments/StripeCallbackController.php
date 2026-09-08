<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\Stripe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where Stripe sends the customer back to.
 *
 * Landing here says only that the customer's browser came back. Whether the
 * shop was paid is settled by asking Stripe, never by what is in this address.
 */
class StripeCallbackController extends GatewayReturn
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $outcome = strtolower((string) $request->query('outcome', 'failure'));

        // Coming back from a completed page, Stripe fills in the session it
        // opened; from a cancelled one it does not, so our own reference is
        // carried instead. Both are looked up inside this shop only.
        $sessionId = (string) $request->query('session_id', '');
        $reference = (string) $request->query('reference', '');

        $payment = match (true) {
            $sessionId !== '' => Payment::where('gateway', Stripe::KEY)
                ->where('gateway_payment_id', $sessionId)->first(),
            $reference !== '' => Payment::where('gateway', Stripe::KEY)
                ->where('reference', $reference)->first(),
            default => null,
        };

        return $this->finish($payment, PaymentMethod::where('gateway', Stripe::KEY)->first(), $outcome);
    }
}
