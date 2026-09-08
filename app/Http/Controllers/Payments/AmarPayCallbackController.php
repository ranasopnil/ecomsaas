<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\AmarPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where AmarPay posts the customer back to, whether they paid, failed or
 * backed out. Which of the three it was is in the address we gave it.
 *
 * What is posted here is a nudge to go and look, not proof. Whether the shop
 * was paid is settled by asking AmarPay about our own transaction.
 */
class AmarPayCallbackController extends GatewayReturn
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $outcome = strtolower((string) $request->query('outcome', 'failure'));
        $reference = trim((string) $request->input('mer_txnid', ''));

        // Looked up inside this shop only, so one shop can never settle
        // another shop's payment.
        $payment = $reference === ''
            ? null
            : Payment::where('gateway', AmarPay::KEY)->where('reference', $reference)->first();

        return $this->finish($payment, PaymentMethod::where('gateway', AmarPay::KEY)->first(), $outcome);
    }
}
