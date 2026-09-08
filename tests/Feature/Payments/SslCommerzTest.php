<?php

namespace Tests\Feature\Payments;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Models\Domain;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Payments\Gateways\SslCommerz;
use App\Services\Payments\PaymentProcessor;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SSLCommerz, using one shop's own store account.
 *
 * Nothing here touches the real SSLCommerz. Every answer is a stand-in, so
 * these prove our side of the conversation: that we never believe what is
 * posted to us, ask SSLCommerz about our own transaction instead, refuse an
 * amount that does not match the order, and never take the same money twice.
 */
class SslCommerzTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected const STORE_ID = 'dhakashop0live';

    protected const STORE_PASSWORD = 'dhakashop0live@ssl';

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

    protected function sslcommerz(bool $enabled = true): PaymentMethod
    {
        $method = new PaymentMethod([
            'tenant_id' => $this->store->id,
            'gateway' => SslCommerz::KEY,
            'is_enabled' => $enabled,
            'settings' => ['store_id' => self::STORE_ID, 'sandbox' => true],
        ]);

        $method->credentials = ['store_password' => self::STORE_PASSWORD];
        $method->save();

        return $method;
    }

    /**
     * What SSLCommerz answers when a session is opened.
     */
    protected function opened(array $with = []): array
    {
        return [
            'status' => 'SUCCESS',
            'sessionkey' => 'F7A1C2D3E4B5A6978',
            'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/testcde7a1c2d3e4b5a6978',
            'storeBanner' => '', 'storeLogo' => '',
            ...$with,
        ];
    }

    /**
     * One transaction, as the transaction query hands it back.
     */
    protected function transaction(string $reference, array $with = []): array
    {
        return [
            'APIConnect' => 'DONE',
            'no_of_trans_found' => 1,
            'element' => [[
                'status' => 'VALID',
                'tran_id' => $reference,
                'val_id' => '2609081215330aBcDeFgHiJk',
                'bank_tran_id' => '2609081215330DHAKA1234',
                'amount' => '1000.00',
                'currency_amount' => '1000.00',
                'store_amount' => '975.50',
                'currency' => 'BDT',
                'card_type' => 'VISA-Dutch Bangla',
                'risk_level' => '0',
                ...$with,
            ]],
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

    protected function payment(): Payment
    {
        return Payment::factory()->initiated('F7A1C2D3E4B5A6978')->create([
            'tenant_id' => $this->store->id, 'gateway' => SslCommerz::KEY,
        ]);
    }

    /**
     * A notice signed the way SSLCommerz signs one.
     *
     * @return array<string, string>
     */
    protected function notice(string $reference, string $status = 'VALID', array $with = [], ?string $password = null): array
    {
        $posted = [
            'tran_id' => $reference,
            'status' => $status,
            'val_id' => '2609081215330aBcDeFgHiJk',
            'amount' => '1000.00',
            'currency' => 'BDT',
            'bank_tran_id' => '2609081215330DHAKA1234',
            ...$with,
        ];

        $posted['verify_key'] = implode(',', array_keys($posted));

        $fields = $posted;
        unset($fields['verify_key']);
        $fields['store_passwd'] = md5($password ?? self::STORE_PASSWORD);
        ksort($fields);

        $pairs = [];

        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $posted['verify_sign'] = md5(implode('&', $pairs));

        return $posted;
    }

    /*
     * ---------------------------------------------------------------
     * Checking a shop's own store details
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_check_their_store_details_work(): void
    {
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response([
            'APIConnect' => 'DONE', 'no_of_trans_found' => 0, 'element' => [],
        ])]);

        $this->sslcommerz();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', SslCommerz::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'ok'
                && str_contains($this->toast($params)['text'], 'test system'));

        // Checking the details must not open a payment or move anything.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'merchantTransIDvalidationAPI.php'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'gwprocess'));
    }

    public function test_wrong_store_details_are_explained_in_plain_words(): void
    {
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'INVALID_REQUEST'])]);

        $this->sslcommerz();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', SslCommerz::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'bad'
                && str_contains($this->toast($params)['text'], 'did not accept that store ID or password'));
    }

    /*
     * ---------------------------------------------------------------
     * Taking a payment
     * ---------------------------------------------------------------
     */

    public function test_starting_a_payment_sends_the_customer_to_sslcommerz(): void
    {
        Http::fake(['*/gwprocess/v4/api.php' => Http::response($this->opened())]);

        $result = $this->processor()->start(
            $this->sslcommerz(),
            new Money(100000, 'BDT', 2),
            'https://dhaka.test/payments/sslcommerz/callback',
        );

        $this->assertStringStartsWith('https://sandbox.sslcommerz.com/EasyCheckOut/', $result['redirect_url']);
        $this->assertSame(Payment::STATUS_INITIATED, $result['payment']->status);
        $this->assertSame('F7A1C2D3E4B5A6978', $result['payment']->gateway_payment_id);
        $this->assertTrue($result['payment']->is_sandbox);

        $reference = $result['payment']->reference;

        Http::assertSent(function (Request $r) use ($reference) {
            $data = $r->data();

            return str_ends_with($r->url(), '/gwprocess/v4/api.php')
                && $data['store_id'] === self::STORE_ID
                && $data['total_amount'] === '1000.00'
                && $data['currency'] === 'BDT'
                && $data['tran_id'] === $reference
                // The three ways it can end, each saying which it was.
                && str_contains($data['success_url'], 'outcome=success')
                && str_contains($data['fail_url'], 'outcome=failure')
                && str_contains($data['cancel_url'], 'outcome=cancel')
                && str_contains($data['ipn_url'], '/payments/sslcommerz/ipn');
        });
    }

    public function test_sslcommerz_refusing_to_open_a_payment_is_said_plainly(): void
    {
        Http::fake(['*/gwprocess/v4/api.php' => Http::response([
            'status' => 'FAILED', 'failedreason' => 'Store Credential Error Or Store is De-active',
        ])]);

        $this->expectExceptionMessage('did not accept that store ID or password');

        $this->processor()->start(
            $this->sslcommerz(),
            new Money(100000, 'BDT', 2),
            'https://dhaka.test/payments/sslcommerz/callback',
        );
    }

    public function test_a_paid_transaction_is_recorded_as_a_payment(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $settled = $this->processor()->settle($payment, $this->sslcommerz(), 'success');

        $this->assertTrue($settled->isPaid());
        $this->assertSame('2609081215330DHAKA1234', $settled->gateway_transaction_id);
        $this->assertSame('VISA-Dutch Bangla', $settled->payer_account);
        $this->assertNotNull($settled->paid_at);

        // Rule seven: the follow-up work is written with the payment.
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());

        // It asked about our own transaction id, not about anything posted.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'tran_id='.$payment->reference));
    }

    public function test_a_transaction_for_the_wrong_amount_is_never_treated_as_paid(): void
    {
        $payment = $this->payment();

        // The order is for 1,000 Taka. SSLCommerz says 10 was paid.
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response(
            $this->transaction($payment->reference, ['amount' => '10.00', 'currency_amount' => '10.00'])
        )]);

        $settled = $this->processor()->settle($payment, $this->sslcommerz(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertStringContainsString('does not match this order', $settled->failure_reason);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_a_transaction_in_another_currency_is_never_treated_as_paid(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response(
            $this->transaction($payment->reference, ['currency' => 'USD'])
        )]);

        $settled = $this->processor()->settle($payment, $this->sslcommerz(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_somebody_elses_transaction_is_not_ours(): void
    {
        $payment = $this->payment();

        // The right shape, the wrong transaction id.
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response(
            $this->transaction('P260101SOMEBODYELSE')
        )]);

        $settled = $this->processor()->settle($payment, $this->sslcommerz(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertStringContainsString('no record', $settled->failure_reason);
    }

    public function test_landing_back_saying_success_is_not_itself_a_payment(): void
    {
        $payment = $this->payment();

        // The browser says success; SSLCommerz says it failed. SSLCommerz wins.
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response(
            $this->transaction($payment->reference, ['status' => 'FAILED'])
        )]);

        $settled = $this->processor()->settle($payment, $this->sslcommerz(), 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_coming_back_twice_takes_the_money_once(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $method = $this->sslcommerz();

        $this->processor()->settle($payment, $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');

        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'execute')->count());
    }

    public function test_a_customer_who_backs_out_is_charged_nothing(): void
    {
        Http::fake();

        $settled = $this->processor()->settle($this->payment(), $this->sslcommerz(), 'cancel');

        $this->assertSame(Payment::STATUS_CANCELLED, $settled->status);
        Http::assertNothingSent();
    }

    public function test_a_payment_lost_on_the_way_back_is_found_by_asking_sslcommerz(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $found = $this->processor()->reconcile($payment, $this->sslcommerz());

        $this->assertTrue($found->isPaid());
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_sslcommerz_only_takes_taka(): void
    {
        Http::fake();

        $this->expectExceptionMessage('only takes Bangladeshi Taka');

        $this->processor()->start(
            $this->sslcommerz(),
            new Money(10000, 'USD', 2),
            'https://dhaka.test/payments/sslcommerz/callback',
        );
    }

    /*
     * ---------------------------------------------------------------
     * Coming back from the payment page
     * ---------------------------------------------------------------
     */

    public function test_the_customer_is_posted_back_and_told_the_outcome(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $this->sslcommerz();
        $url = $this->shopUrl();
        $reference = $payment->reference;
        Tenancy::forget();

        $this->post($url.'/payments/sslcommerz/callback?outcome=success', [
            'tran_id' => $reference, 'status' => 'VALID',
        ])->assertOk()->assertSee('Payment received');

        Tenancy::set($this->store);
        $this->assertTrue(Payment::first()->isPaid());
    }

    /*
     * ---------------------------------------------------------------
     * What SSLCommerz tells the shop directly
     * ---------------------------------------------------------------
     */

    public function test_a_signed_notice_finishes_the_payment(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $this->sslcommerz();
        $url = $this->shopUrl();
        $notice = $this->notice($payment->reference);
        Tenancy::forget();

        $this->post($url.'/payments/sslcommerz/ipn', $notice)->assertOk();

        Tenancy::set($this->store);
        $this->assertTrue(Payment::first()->isPaid());
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_an_unsigned_or_forged_notice_changes_nothing(): void
    {
        $payment = $this->payment();

        Http::fake();

        $this->sslcommerz();
        $url = $this->shopUrl();
        $forged = $this->notice($payment->reference, password: 'not-the-store-password');
        $unsigned = ['tran_id' => $payment->reference, 'status' => 'VALID'];
        Tenancy::forget();

        $this->post($url.'/payments/sslcommerz/ipn', $forged)->assertStatus(400);
        $this->post($url.'/payments/sslcommerz/ipn', $unsigned)->assertStatus(400);

        Tenancy::set($this->store);
        $this->assertFalse(Payment::first()->isPaid());

        // It never even asked SSLCommerz: a forged notice is dropped first.
        Http::assertNothingSent();
    }

    public function test_the_same_notice_arriving_twice_takes_the_money_once(): void
    {
        $payment = $this->payment();

        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response($this->transaction($payment->reference))]);

        $this->sslcommerz();
        $url = $this->shopUrl();
        $notice = $this->notice($payment->reference);
        Tenancy::forget();

        foreach (range(1, 3) as $ignored) {
            $this->post($url.'/payments/sslcommerz/ipn', $notice)->assertOk();
        }

        Tenancy::set($this->store);
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'ipn.valid')->count());
    }

    public function test_a_shop_that_has_not_set_sslcommerz_up_takes_no_notices(): void
    {
        Http::fake();

        $payment = $this->payment();
        $url = $this->shopUrl();
        $notice = $this->notice($payment->reference);
        Tenancy::forget();

        $this->post($url.'/payments/sslcommerz/ipn', $notice)->assertNotFound();
    }

    /*
     * ---------------------------------------------------------------
     * Giving money back
     * ---------------------------------------------------------------
     */

    public function test_a_refund_is_a_new_record_and_leaves_the_payment_alone(): void
    {
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response([
            'APIConnect' => 'DONE', 'status' => 'success',
            'bank_tran_id' => '2609081215330DHAKA1234',
            'refund_ref_id' => 'REF260908123456', 'errorReason' => '',
        ])]);

        $method = $this->sslcommerz();
        $payment = Payment::factory()->paid('2609081215330DHAKA1234')->create([
            'tenant_id' => $this->store->id, 'gateway' => SslCommerz::KEY,
        ]);

        $refund = $this->processor()->refund($payment, $method, new Money(40000, 'BDT', 2), 'Sent the wrong size');

        $this->assertSame(PaymentRefund::STATUS_COMPLETED, $refund->status);
        $this->assertSame('REF260908123456', $refund->gateway_refund_id);

        // Rule five: the payment itself is untouched.
        $this->assertSame(100000, $payment->fresh()->amount_minor);
        $this->assertSame(60000, $payment->fresh()->refundableAmount()->minor);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'refund_amount=400.00')
            && str_contains($r->url(), 'bank_tran_id=2609081215330DHAKA1234'));
    }

    /*
     * ---------------------------------------------------------------
     * Keeping the shop's password to itself
     * ---------------------------------------------------------------
     */

    public function test_the_store_password_never_reaches_a_message_a_person_could_read(): void
    {
        Http::fake(['*/merchantTransIDvalidationAPI.php*' => Http::response([
            'APIConnect' => 'INVALID_REQUEST', 'reason' => 'store_passwd '.self::STORE_PASSWORD.' rejected',
        ])]);

        $this->sslcommerz();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', SslCommerz::KEY)
            ->assertDispatched('toast', fn ($event, $params) => ! str_contains($this->toast($params)['text'], self::STORE_PASSWORD));
    }

    public function test_the_payments_screen_shows_sslcommerz_with_its_own_mark(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->assertSee('SSLCommerz')
            ->assertSee('#1B3E70', false);
    }

    public function test_the_form_tells_the_shopkeeper_where_sslcommerz_should_send_its_notices(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', SslCommerz::KEY)
            ->assertSee('/payments/sslcommerz/ipn', false);
    }
}
