<?php

namespace App\Services\Payments;

use App\Exceptions\GatewayFailed;
use App\Facades\Tenancy;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Payments\Contracts\OnlineGateway;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Starting, finishing and reconciling payments.
 *
 * Two rules shape all of this. A gateway may tell us the same thing twice, so
 * nothing here acts on a message more than once. And anything that has to
 * happen because money moved is written to the outbox in the same transaction
 * as the payment, so it cannot be lost if the queue is.
 */
class PaymentProcessor
{
    public function __construct(protected GatewayFactory $gateways) {}

    /**
     * Open a payment and return where to send the customer.
     *
     * @return array{payment: Payment, redirect_url: string}
     */
    public function start(PaymentMethod $method, Money $amount, string $callbackUrl, array $attributes = []): array
    {
        if (! $method->isReady()) {
            throw GatewayFailed::notConfigured($method->gateway);
        }

        if ($amount->minor <= 0) {
            throw GatewayFailed::refused($method->gateway, 'A payment has to be for more than nothing.');
        }

        $payment = new Payment([
            'tenant_id' => Tenancy::id(),
            'gateway' => $method->gateway,
            'reference' => Payment::newReference(),
            'status' => Payment::STATUS_PENDING,
            ...$attributes,
        ]);

        $payment->amount = $amount;
        $payment->save();

        $url = $this->gateways->for($method)->start($payment, $callbackUrl);

        return ['payment' => $payment->refresh(), 'redirect_url' => $url];
    }

    /**
     * The customer has come back from the gateway. Take the money if they
     * approved it, and write down the outcome exactly once.
     *
     * $outcome is what the gateway said in the return address: 'success',
     * 'failure' or 'cancel'. It is a hint, not proof — only the gateway's own
     * answer to our execute call decides whether money actually moved.
     */
    public function settle(Payment $payment, PaymentMethod $method, string $outcome): Payment
    {
        // Already decided. A second visit to the same return address, a
        // refresh, a back button — all land here and change nothing.
        if ($payment->isFinished()) {
            return $payment;
        }

        if ($outcome !== 'success') {
            return $this->finish(
                $payment,
                $outcome === 'cancel' ? Payment::STATUS_CANCELLED : Payment::STATUS_FAILED,
                ['failure_reason' => $outcome === 'cancel'
                    ? 'The customer cancelled it.'
                    : $method->name().' did not approve it.'],
            );
        }

        // Claim the right to execute. The unique key on the event is what makes
        // this safe: two callbacks arriving together, only one gets through.
        if (! $this->claim($payment, 'execute')) {
            return $payment->refresh();
        }

        $gateway = $this->gateways->for($method);

        try {
            $body = $gateway->complete($payment);
        } catch (GatewayFailed $e) {
            return $this->finish($payment, Payment::STATUS_FAILED, ['failure_reason' => $e->getMessage()]);
        }

        return $this->recordOutcome($payment, $gateway, $body);
    }

    /**
     * Ask the gateway what really happened to a payment we are unsure about.
     *
     * This is the safety net: if we lost the customer between approving the
     * payment and telling us, this finds the money.
     */
    public function reconcile(Payment $payment, PaymentMethod $method): Payment
    {
        if ($payment->isFinished() || $payment->gateway_payment_id === null) {
            return $payment;
        }

        $gateway = $this->gateways->for($method);

        try {
            $body = $gateway->status($payment);
        } catch (GatewayFailed) {
            return $payment;
        }

        if (! $gateway->saysCompleted($body)) {
            return $payment;
        }

        return $this->recordOutcome($payment, $gateway, $body);
    }

    /**
     * Give money back as a new record. The original payment is never rewritten.
     */
    public function refund(Payment $payment, PaymentMethod $method, Money $amount, ?string $reason = null): PaymentRefund
    {
        if (! $payment->isPaid()) {
            throw GatewayFailed::refused($payment->gateway, 'That payment was never completed, so there is nothing to give back.');
        }

        if ($amount->minor <= 0 || $amount->minor > $payment->refundableAmount()->minor) {
            throw GatewayFailed::refused($payment->gateway, 'That is more than is left to refund on this payment.');
        }

        $refund = new PaymentRefund([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->id,
            'status' => PaymentRefund::STATUS_PENDING,
            'reason' => $reason,
        ]);

        $refund->amount = $amount;
        $refund->save();

        $gateway = $this->gateways->for($method);

        try {
            $body = $gateway->refund($refund);
        } catch (GatewayFailed $e) {
            $refund->update(['status' => PaymentRefund::STATUS_FAILED, 'meta' => ['error' => $e->getMessage()]]);

            throw $e;
        }

        DB::transaction(function () use ($refund, $gateway, $body) {
            $refund->update([
                'status' => PaymentRefund::STATUS_COMPLETED,
                'gateway_refund_id' => $gateway->refundIdOf($body),
                'meta' => $this->safe($body),
            ]);

            OutboxEvent::create([
                'tenant_id' => $refund->tenant_id,
                'type' => 'payment.refunded',
                'payload' => ['payment_id' => $refund->payment_id, 'refund_id' => $refund->id],
                'available_at' => now(),
            ]);
        });

        return $refund->refresh();
    }

    /*
     * ---------------------------------------------------------------
     */

    /**
     * Write down what the gateway said, and the outbox row that goes with it,
     * in one transaction. Either both land or neither does.
     */
    protected function recordOutcome(Payment $payment, OnlineGateway $gateway, array $body): Payment
    {
        $outcome = $gateway->outcomeOf($body);

        if (! $gateway->saysCompleted($body)) {
            return $this->finish($payment, Payment::STATUS_FAILED, [
                'failure_reason' => $outcome['failure_reason'] ?? 'The payment was not completed.',
                'meta' => $this->safe($body),
            ]);
        }

        return $this->finish($payment, Payment::STATUS_COMPLETED, [
            'gateway_transaction_id' => $outcome['transaction_id'] ?? null,
            'payer_account' => $outcome['payer_account'] ?? null,
            'paid_at' => now(),
            'meta' => $this->safe($body),
        ]);
    }

    protected function finish(Payment $payment, string $status, array $attributes = []): Payment
    {
        return DB::transaction(function () use ($payment, $status, $attributes) {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->isFinished()) {
                return $fresh ?? $payment;
            }

            $fresh->forceFill(['status' => $status, ...$attributes])->save();

            if ($status === Payment::STATUS_COMPLETED) {
                OutboxEvent::create([
                    'tenant_id' => $fresh->tenant_id,
                    'type' => 'payment.completed',
                    'payload' => [
                        'payment_id' => $fresh->id,
                        'reference' => $fresh->reference,
                        'order_id' => $fresh->order_id,
                    ],
                    'available_at' => now(),
                ]);
            }

            return $fresh;
        });
    }

    /**
     * Take the right to do something once, using the database's unique key
     * rather than a check-then-act that two requests could both pass.
     */
    protected function claim(Payment $payment, string $type): bool
    {
        try {
            // In its own transaction so that being second is a savepoint
            // rolled back, not a whole request's transaction left unusable.
            DB::transaction(fn () => PaymentEvent::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'event_id' => $payment->gateway_payment_id.':'.$type,
                'type' => $type,
            ]));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Keep a gateway's answer for the record, minus anything that looks like a
     * credential. Nothing secret is ever stored twice or logged.
     *
     * @return array<string, mixed>
     */
    protected function safe(array $body): array
    {
        return collect($body)
            ->except([
                'id_token', 'refresh_token', 'app_key', 'app_secret', 'password', 'username',
                'client_secret', 'secret_key', 'publishable_key', 'webhook_secret',
            ])
            ->all();
    }
}
