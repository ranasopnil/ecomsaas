<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\SslCommerz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where SSLCommerz posts the customer back to, whether they paid, failed or
 * backed out. Which of the three it was is in the address we gave it.
 *
 * What is posted here is a nudge to go and look, not proof. Whether the shop
 * was paid is settled by asking SSLCommerz about our own transaction.
 */
class SslCommerzCallbackController extends GatewayReturn
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $outcome = strtolower((string) $request->query('outcome', 'failure'));
        $reference = trim((string) $request->input('tran_id', ''));

        // Looked up inside this shop only, so one shop can never settle
        // another shop's payment.
        $payment = $reference === ''
            ? null
            : Payment::where('gateway', SslCommerz::KEY)->where('reference', $reference)->first();

        return $this->finish($payment, PaymentMethod::where('gateway', SslCommerz::KEY)->first(), $outcome);
    }
}
