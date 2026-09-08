<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\Orders\SettleOrder;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Console\Command;

/**
 * Finds money that arrived without anybody telling us.
 *
 * A customer can pay and then close the tab, lose their connection, or have
 * their phone die on the way back. The shop is owed that money and the order
 * is sitting there waiting. This asks each gateway, for each payment left
 * open, what actually happened.
 *
 * It can only ever find a payment, never lose one: a payment that is still
 * unpaid is left exactly as it was, for the next sweep to ask about again.
 * Nothing here cancels an order or puts stock back — that is the shopkeeper's
 * decision, not a background job's.
 *
 * Every shop is asked inside its own context, so this never reaches across
 * from one shop to another.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile
                            {--minutes=15 : How long a payment must have been open before it is asked about}
                            {--limit=100 : How many payments to ask about per shop, per run}';

    protected $description = 'Ask each gateway about payments the customer never came back from';

    public function handle(PaymentProcessor $processor, GatewayFactory $gateways, SettleOrder $orders): int
    {
        $olderThan = now()->subMinutes(max(1, (int) $this->option('minutes')));
        $limit = max(1, (int) $this->option('limit'));

        $asked = 0;
        $found = 0;

        Tenant::query()->each(function (Tenant $store) use ($processor, $gateways, $orders, $olderThan, $limit, &$asked, &$found) {
            Tenancy::run($store, function () use ($store, $processor, $gateways, $orders, $olderThan, $limit, &$asked, &$found) {
                $open = Payment::query()
                    ->where('status', Payment::STATUS_INITIATED)
                    ->where('initiated_at', '<=', $olderThan)
                    ->orderBy('initiated_at')
                    ->take($limit)
                    ->get();

                if ($open->isEmpty()) {
                    return;
                }

                $methods = PaymentMethod::query()->get()->keyBy('gateway');

                foreach ($open as $payment) {
                    $method = $methods->get($payment->gateway);

                    if ($method === null || ! $gateways->isDriven($payment->gateway)) {
                        continue;
                    }

                    $asked++;

                    $settled = $processor->reconcile($payment, $method);

                    if (! $settled->isPaid()) {
                        continue;
                    }

                    $found++;

                    // The order was waiting on this. Finishing it is safe to
                    // run twice, so a customer who turns up at the same moment
                    // changes nothing.
                    $order = $settled->order_id === null ? null : Order::find($settled->order_id);

                    if ($order !== null) {
                        $orders->paid($order, $settled);
                    }

                    $this->line("  found {$settled->reference} paid at {$settled->gateway} for {$store->name}");
                }
            });
        });

        $this->info("Asked about {$asked} open payment(s); {$found} had in fact been paid.");

        return self::SUCCESS;
    }
}
