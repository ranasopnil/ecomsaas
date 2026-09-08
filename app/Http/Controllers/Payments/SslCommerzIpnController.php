<?php

namespace App\Http\Controllers\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\Gateways\SslCommerz;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What SSLCommerz tells the shop directly — its instant payment notice.
 *
 * Anyone can post to this address, so nothing is acted on until the hash
 * SSLCommerz signs it with, using the shop's own store password, checks out.
 * Even then the notice only sends us to ask SSLCommerz what happened; it is
 * never itself the answer.
 */
class SslCommerzIpnController extends GatewayNotice
{
    /** What SSLCommerz calls a transaction, and what that means for us. */
    protected const MEANING = [
        'VALID' => 'success',
        'VALIDATED' => 'success',
        'FAILED' => 'failure',
        'CANCELLED' => 'cancel',
        'EXPIRED' => 'failure',
        'UNATTEMPTED' => 'failure',
    ];

    public function __invoke(Request $request, GatewayFactory $gateways): Response
    {
        // The shop is already known: this address is the shop's own.
        $method = PaymentMethod::where('gateway', SslCommerz::KEY)->first();

        if ($method === null || ! $method->hasSecret('store_password')) {
            return response('This shop is not taking SSLCommerz notices.', 404);
        }

        $posted = $request->all();
        $driver = $gateways->for($method);

        if (! $driver instanceof SslCommerz || ! $driver->noticeIsSigned($posted)) {
            // 400 rather than 200: SSLCommerz should know it was refused, and
            // a forged notice must never look accepted.
            return response('Signature did not check out.', 400);
        }

        $reference = trim((string) ($posted['tran_id'] ?? ''));
        $status = strtoupper(trim((string) ($posted['status'] ?? '')));

        if ($reference === '' || ! isset(self::MEANING[$status])) {
            // Nothing we act on. Answering 200 stops SSLCommerz retrying a
            // notice we were never going to do anything with.
            return response('Nothing to do.', 200);
        }

        $payment = Payment::where('gateway', SslCommerz::KEY)->where('reference', $reference)->first();

        if ($payment === null) {
            return response('No such payment here.', 200);
        }

        // SSLCommerz gives a validation id to every transaction it settles;
        // for anything else the transaction and its state name it well enough
        // to notice the same notice twice.
        $valId = trim((string) ($posted['val_id'] ?? ''));
        $eventId = $valId !== '' ? 'val:'.$valId : 'ipn:'.$reference.':'.$status;

        return $this->actOnce(
            $payment,
            $method,
            $eventId,
            'ipn.'.strtolower($status),
            ['tran_id' => $reference, 'status' => $status, 'val_id' => $valId ?: null],
            self::MEANING[$status],
        );
    }
}
