<?php

namespace App\Http\Controllers\Payments;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Orders\SettleOrder;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * What every gateway's return address does once it knows which payment the
 * customer is coming back from.
 *
 * The customer is standing in front of the answer, so the order is finished
 * here rather than left to a worker. Every step of it is safe to run twice:
 * a reload, a back button, or the gateway sending the customer round again
 * all land here and change nothing after the first time.
 */
abstract class GatewayReturn extends Controller
{
    public function __construct(
        protected PaymentProcessor $processor,
        protected SettleOrder $orders,
    ) {}

    /**
     * $outcome is what the gateway said in the return address: 'success',
     * 'cancel' or anything else. It is a hint, never proof — the gateway's
     * own answer is what decides whether the shop was paid.
     */
    protected function finish(?Payment $payment, ?PaymentMethod $method, string $outcome): View|RedirectResponse
    {
        if ($payment === null || $method === null) {
            return view('payments.result', ['payment' => null, 'store' => Tenancy::current()]);
        }

        $payment = $this->processor->settle($payment, $method, $outcome);

        $order = $payment->order_id === null ? null : Order::find($payment->order_id);

        if ($order !== null) {
            $order = $payment->isPaid()
                ? $this->orders->paid($order, $payment)
                : $this->orders->cancelled($order, $payment->failure_reason ?: 'The payment did not go through.');

            return redirect()->to(
                route('storefront.order', ['reference' => $order->reference]).'?token='.$order->view_token
            );
        }

        return view('payments.result', [
            'payment' => $payment,
            'store' => Tenancy::current(),
        ]);
    }
}
