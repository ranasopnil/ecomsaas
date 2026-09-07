<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'gateway' => 'bkash',
            'reference' => Payment::newReference(),
            'amount_minor' => 100000,
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'status' => Payment::STATUS_PENDING,
            'is_sandbox' => true,
        ];
    }

    public function initiated(string $gatewayPaymentId = 'TR0011abcdef'): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_INITIATED,
            'gateway_payment_id' => $gatewayPaymentId,
            'initiated_at' => now(),
        ]);
    }

    public function paid(string $trxId = 'AAB12CD34E'): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_COMPLETED,
            'gateway_payment_id' => 'TR0011abcdef',
            'gateway_transaction_id' => $trxId,
            'paid_at' => now(),
        ]);
    }
}
