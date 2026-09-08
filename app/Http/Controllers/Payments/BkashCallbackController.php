<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\Bkash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where bKash sends the customer back to after they approve or abandon a
 * payment. bKash may send them here more than once; that is expected and
 * changes nothing after the first time.
 */
class BkashCallbackController extends GatewayReturn
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $gatewayPaymentId = (string) $request->query('paymentID', '');
        $outcome = strtolower((string) $request->query('status', 'failure'));

        // Scoped to the shop whose address this request came in on, so one
        // shop can never settle another shop's payment.
        $payment = $gatewayPaymentId === ''
            ? null
            : Payment::where('gateway', Bkash::KEY)->where('gateway_payment_id', $gatewayPaymentId)->first();

        return $this->finish($payment, PaymentMethod::where('gateway', Bkash::KEY)->first(), $outcome);
    }
}
