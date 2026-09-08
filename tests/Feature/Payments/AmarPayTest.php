<?php

namespace Tests\Feature\Payments;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Payments\Gateways\AmarPay;
use App\Services\Payments\PaymentProcessor;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AmarPay, using one shop's own store account.
 *
 * Nothing here touches the real AmarPay. Every answer is a stand-in, so these
 * prove our side of the conversation: that we never believe what is posted to
 * us, ask AmarPay about our own transaction instead, refuse an amount that
 * does not match the order, and find money that arrived while nobody was
 * looking.
 */
class AmarPayTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected const STORE_ID = 'aamarpaytest';

    protected const SIGNATURE_KEY = 'dbb74894e82415a2f7ff';

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'name' => 'Dhaka Grocer', 'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
            'email' => 'shop@dhakagrocer.test',
        ]);

        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing([
            'products' => 50, 'online_payments' => true,
        ])->create());

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function amarpay(bool $enabled = true): PaymentMethod
    {
        $method = new PaymentMethod([
            'tenant_id' => $this->store->id,
            'gateway' => AmarPay::KEY,
            'is_enabled' => $enabled,
            'settings' => ['store_id' => self::STORE_ID, 'sandbox' => true],
        ]);

        $method->credentials = ['signature_key' => self::SIGNATURE_KEY];
        $method->save();

        return $method;
    }

    /**
     * What AmarPay answers when a payment is opened.
     */
    protected function opened(array $with = []): array
    {
        return [
            'result' => 'true',
            'payment_url' => 'https://sandbox.aamarpay.com/paynow.php?track=AAMARPAY1234567890',
            ...$with,
        ];
    }

    /**
     * What AmarPay says about one of our transactions.
     */
    protected function checked(string $reference, array $with = []): array
    {
        return [
            'pay_status' => 'Successful',
            'status_code' => '2',
            'mer_txnid' => $reference,
            'pg_txnid' => 'AAMARPAY1234567890',
            'amount' => '1000.00',
            'currency' => 'BDT',
            'payment_processor' => 'bKash',
            'cus_name' => 'A Shopper',
            'cus_email' => 'shop@dhakagrocer.test',
            'pay_time' => '2026-09-08 12:15:33',
            ...$with,
        ];
    }

    protected function processor(): PaymentProcessor
    {
        return app(PaymentProcessor::class);
    }

    /**
     * @return array{text: string, tone: string|null}
     */
    protected function toast(array $params): array
    {
        return ['text' => '', 'tone' => null, ...($params[0] ?? [])];
    }

    protected function shopUrl(): string
    {
        return 'https://'.Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);
    }

    protected function payment(?Tenant $store = null): Payment
    {
        $store ??= $this->store;

        return Payment::factory()->initiated('AAMARPAY1234567890')->create([
            'tenant_id' => $store->id, 'gateway' => AmarPay::KEY,
        ]);
    }

    protected function orderFor(Payment $payment): Order
    {
        $order = Order::create([
            'tenant_id' => $payment->tenant_id,
            'reference' => 'ORD'.$payment->id,
            'view_token' => str_repeat('t', 40),
            'customer_name' => 'A Shopper',
            'customer_phone' => '01711000000',
            'customer_address' => 'Dhanmondi, Dhaka',
            'payment_gateway' => AmarPay::KEY,
            'payment_id' => $payment->id,
            'goods_minor' => 100000,
            'delivery_minor' => 0,
            'total_minor' => 100000,
            'currency' => 'BDT',
            'currency_exponent' => 2,
        ]);

        $payment->forceFill(['order_id' => $order->id])->save();

        return $order;
    }

    /*
     * ---------------------------------------------------------------
     * Checking a shop's own store details
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_check_their_store_details_work(): void
    {
        Http::fake(['*/trxcheck/request.php*' => Http::response(['pay_status' => '', 'mer_txnid' => ''])]);

        $this->amarpay();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', AmarPay::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'ok');

        // Checking the details must not open a payment or move anything.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'jsonpost'));
    }

    public function test_wrong_store_details_are_explained_in_plain_words(): void
    {
        Http::fake(['*/trxcheck/request.php*' => Http::response(['error' => 'Invalid store id or signature key'])]);

        $this->amarpay();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', AmarPay::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'bad'
                && str_contains($this->toast($params)['text'], 'did not accept that store ID or signature key'));
    }

    /*
     * ---------------------------------------------------------------
     * Taking a payment
     * ---------------------------------------------------------------
     */

    public function test_starting_a_payment_sends_the_customer_to_amarpay(): void
    {
        Http::fake(['*/jsonpost.php' => Http::response($this->opened())]);

        $result = $this->processor()->start(
            $this->amarpay(),
            new Money(100000, 'BDT', 2),
            'https://dhaka.test/payments/amarpay/callback',
        );

        $this->assertSame('https://sandbox.aamarpay.com/paynow.php?track=AAMARPAY1234567890', $result['redirect_url']);
        $this->assertSame(Payment::STATUS_INITIATED, $result['payment']->status);

        // AmarPay's own name for the payment, kept so its screens and ours
        // can be lined up later.
        $this->assertSame('AAMARPAY1234567890', $result['payment']->gateway_payment_id);
        $this->assertTrue($result['payment']->is_sandbox);

        $reference = $result['payment']->reference;

        Http::assertSent(function (Request $r) use ($reference) {
            $data = $r->data();

            return str_ends_with($r->url(), '/jsonpost.php')
                && $data['store_id'] === self::STORE_ID
                && $data['signature_key'] === self::SIGNATURE_KEY
                && $data['amount'] === '1000.00'
                && $data['currency'] === 'BDT'
                && $data['tran_id'] === $reference
                && str_contains($data['success_url'], 'outcome=success')
                && str_contains($data['fail_url'], 'outcome=failure')
                && str_contains($data['cancel_url'], 'outcome=cancel');
        });
    }

    public function test_an_address_sent_back_on_its_own_is_understood_too(): void
    {
        // Some AmarPay stores answer with the address as plain text, and some
        // with only the path.
        Http::fake(['*/jsonpost.php' => Http::response('/paynow.php?track=AAMARPAY1234567890')]);

        $result = $this->processor()->start(
            $this->amarpay(),
            new Money(100000, 'BDT', 2),
            'https://dhaka.test/payments/amarpay/callback',
        );

        $this->assertSame('https://sandbox.aamarpay.com/paynow.php?track=AAMARPAY1234567890', $result['redirect_url']);
    }

    public function test_amarpay_refusing_to_open_a_payment_is_said_plainly(): void
    {
        // AmarPay names the field it did not like rather than saying no.
        Http::fake(['*/jsonpost.php' => Http::response(['signature_key' => 'invalid signature key'])]);

        $this->expectExceptionMessage('did not accept that store ID or signature key');

        $this->processor()->start(
            $this->amarpay(),
            new Money(100000, 'BDT', 2),
            'https://dhaka.test/payments/amarpay/callback',
        );
    }

    public function test_a_paid_transaction_is_recorded_as_a_payment(): void
    {
        $payment = $this->payment();

        Http::fake(['*/trxcheck/request.php*' => Http::response($this->checked($payment->reference))]);

        $settled = $this->processor()->settle($payment, $this->amarpay(), 'success');

        $this->assertTrue($settled->isPaid());
        $this->assertSame('AAMARPAY1234567890', $settled->gateway_transaction_id);
        $this->assertSame('bKash', $settled->payer_account);
        $this->assertNotNull($settled->paid_at);

        // Rule seven: the follow-up work is written with the payment.
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());

        // It asked about our own transaction id, not about anything posted.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'request_id='.$payment->reference));
    }

    public function test_a_transaction_for_the_wrong_amount_is_never_treated_as_paid(): void
    {
        $payment = $this->payment();

        // The order is for 1,000 Taka. AmarPay says 10 was paid.
        Http::fake(['*/trxcheck/request.php*' => Http::response(
            $this->checked($payment->reference, ['amount' => '10.00'])
        )]);

        $settled = $this->processor()->settle($payment, $this->amarpay(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertStringContainsString('does not match this order', $settled->failure_reason);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_a_transaction_in_another_currency_is_never_treated_as_paid(): void
    {
        $payment = $this->payment();

        Http::fake(['*/trxcheck/request.php*' => Http::response(
            $this->checked($payment->reference, ['currency' => 'USD'])
        )]);

        $settled = $this->processor()->settle($payment, $this->amarpay(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
    }

    public function test_an_answer_about_a_different_payment_is_refused(): void
    {
        $payment = $this->payment();

        Http::fake(['*/trxcheck/request.php*' => Http::response($this->checked('P260101SOMEBODYELSE'))]);

        $settled = $this->processor()->settle($payment, $this->amarpay(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertStringContainsString('different payment', $settled->failure_reason);
    }

    public function test_landing_back_saying_success_is_not_itself_a_payment(): void
    {
        $payment = $this->payment();

        // The browser says success; AmarPay says it failed. AmarPay wins.
        Http::fake(['*/trxcheck/request.php*' => Http::response(
            $this->checked($payment->reference, ['pay_status' => 'Failed', 'status_code' => '7'])
        )]);

        $settled = $this->processor()->settle($payment, $this->amarpay(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_coming_back_twice_takes_the_money_once(): void
    {
        $payment = $this->payment();

        Http::fake(['*/trxcheck/request.php*' => Http::response($this->checked($payment->reference))]);

        $method = $this->amarpay();

        $this->processor()->settle($payment, $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');

        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'execute')->count());
    }

    public function test_a_customer_who_backs_out_is_charged_nothing(): void
    {
        Http::fake();

        $settled = $this->processor()->settle($this->payment(), $this->amarpay(), 'cancel');

        $this->assertSame(Payment::STATUS_CANCELLED, $settled->status);
        Http::assertNothingSent();
    }

    public function test_amarpay_only_takes_taka(): void
    {
        Http::fake();

        $this->expectExceptionMessage('only takes Bangladeshi Taka');

        $this->processor()->start(
            $this->amarpay(),
            new Money(10000, 'USD', 2),
            'https://dhaka.test/payments/amarpay/callback',
        );
    }

    public function test_the_customer_is_posted_back_and_told_the_outcome(): void
    {
        $payment = $this->payment();

        Http::fake(['*/trxcheck/request.php*' => Http::response($this->checked($payment->reference))]);

        $this->amarpay();
        $url = $this->shopUrl();
        $reference = $payment->reference;
        Tenancy::forget();

        $this->post($url.'/payments/amarpay/callback?outcome=success', [
            'mer_txnid' => $reference, 'pay_status' => 'Successful',
        ])->assertOk()->assertSee('Payment received');

        Tenancy::set($this->store);
        $this->assertTrue(Payment::first()->isPaid());
    }

    public function test_a_refund_says_plainly_where_it_has_to_be_done(): void
    {
        Http::fake();

        $method = $this->amarpay();
        $payment = Payment::factory()->paid('AAMARPAY1234567890')->create([
            'tenant_id' => $this->store->id, 'gateway' => AmarPay::KEY,
        ]);

        $this->expectExceptionMessage('AmarPay merchant panel');

        $this->processor()->refund($payment, $method, new Money(40000, 'BDT', 2), 'Sent the wrong size');
    }

    public function test_the_signature_key_never_reaches_a_message_a_person_could_read(): void
    {
        Http::fake(['*/trxcheck/request.php*' => Http::response([
            'error' => 'store id / signature key '.self::SIGNATURE_KEY.' rejected',
        ])]);

        $this->amarpay();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', AmarPay::KEY)
            ->assertDispatched('toast', fn ($event, $params) => ! str_contains($this->toast($params)['text'], self::SIGNATURE_KEY));
    }

    public function test_the_payments_screen_shows_amarpay_with_its_own_mark(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->assertSee('AmarPay')
            ->assertSee('#C8102E', false);
    }

    /*
     * ---------------------------------------------------------------
     * Finding money nobody came back to tell us about
     * ---------------------------------------------------------------
     */

    public function test_the_sweep_finds_a_payment_the_customer_never_came_back_from(): void
    {
        $payment = $this->payment();
        $payment->forceFill(['initiated_at' => now()->subHour()])->save();

        $order = $this->orderFor($payment);

        Http::fake(['*/trxcheck/request.php*' => Http::response($this->checked($payment->reference))]);

        $this->amarpay();
        Tenancy::forget();

        $this->artisan('payments:reconcile')->assertExitCode(0);

        Tenancy::set($this->store);

        $this->assertTrue(Payment::first()->isPaid());
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());

        // The order was waiting on that money. It is a real order now.
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_the_sweep_leaves_a_payment_that_was_never_made_alone(): void
    {
        $payment = $this->payment();
        $payment->forceFill(['initiated_at' => now()->subHour()])->save();

        Http::fake(['*/trxcheck/request.php*' => Http::response(['pay_status' => '', 'mer_txnid' => ''])]);

        $this->amarpay();
        Tenancy::forget();

        $this->artisan('payments:reconcile')->assertExitCode(0);

        Tenancy::set($this->store);

        // Still open, for the next sweep to ask about again. Never marked
        // failed on a gateway's silence.
        $this->assertSame(Payment::STATUS_INITIATED, Payment::first()->status);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_the_sweep_ignores_payments_that_have_only_just_started(): void
    {
        $this->payment();

        Http::fake();

        $this->amarpay();
        Tenancy::forget();

        // Somebody may still be standing at the payment page. Asking now
        // would be asking about a payment that is going perfectly well.
        $this->artisan('payments:reconcile')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_the_sweep_never_reaches_across_from_one_shop_to_another(): void
    {
        $mine = $this->payment();
        $mine->forceFill(['initiated_at' => now()->subHour()])->save();
        $this->amarpay();

        // A second shop, with its own AmarPay account and its own payment.
        $other = Tenant::factory()->create([
            'name' => 'Chittagong Grocer', 'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['online_payments' => true])->create());

        $theirs = Tenancy::run($other, function () use ($other) {
            $payment = Payment::factory()->initiated('AAMARPAYOTHERSHOP')->create([
                'tenant_id' => $other->id, 'gateway' => AmarPay::KEY,
            ]);
            $payment->forceFill(['initiated_at' => now()->subHour()])->save();

            $method = new PaymentMethod([
                'tenant_id' => $other->id, 'gateway' => AmarPay::KEY, 'is_enabled' => true,
                'settings' => ['store_id' => 'othershop', 'sandbox' => true],
            ]);
            $method->credentials = ['signature_key' => 'other-shop-key'];
            $method->save();

            return $payment;
        });

        // Only this shop's transaction was ever paid.
        Http::fake([
            '*request_id='.$mine->reference.'*' => Http::response($this->checked($mine->reference)),
            '*' => Http::response(['pay_status' => '', 'mer_txnid' => '']),
        ]);

        Tenancy::forget();
        $this->artisan('payments:reconcile')->assertExitCode(0);

        Tenancy::run($this->store, fn () => $this->assertTrue(Payment::find($mine->id)->isPaid()));
        Tenancy::run($other, fn () => $this->assertSame(
            Payment::STATUS_INITIATED,
            Payment::find($theirs->id)->status,
        ));

        // Each shop was asked with its own store details and nobody else's.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'signature_key='.self::SIGNATURE_KEY)
            && str_contains($r->url(), 'request_id='.$mine->reference));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'signature_key=other-shop-key')
            && str_contains($r->url(), 'request_id='.$theirs->reference));
    }
}
