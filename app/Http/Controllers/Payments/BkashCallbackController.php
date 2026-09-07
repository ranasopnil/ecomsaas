<?php

namespace App\Http\Controllers\Payments;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\Bkash;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where bKash sends the customer back to after they approve or abandon a
 * payment. bKash may send them here more than once; that is expected and
 * changes nothing after the first time.
 */
class BkashCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentProcessor $processor): View
    {
        $gatewayPaymentId = (string) $request->query('paymentID', '');
        $outcome = strtolower((string) $request->query('status', 'failure'));

        // Scoped to the shop whose address this request came in on, so one
        // shop can never settle another shop's payment.
        $payment = $gatewayPaymentId === ''
            ? null
            : Payment::where('gateway', Bkash::KEY)->where('gateway_payment_id', $gatewayPaymentId)->first();

        $method = PaymentMethod::where('gateway', Bkash::KEY)->first();

        if ($payment === null || $method === null) {
            return view('payments.result', ['payment' => null, 'store' => Tenancy::current()]);
        }

        return view('payments.result', [
            'payment' => $processor->settle($payment, $method, $outcome),
            'store' => Tenancy::current(),
        ]);
    }
}
