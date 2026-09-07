<?php

namespace Tests\Feature\Payments;

use App\Exceptions\GatewayFailed;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\PaymentProcessor;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * bKash, using one shop's own merchant account.
 *
 * Nothing here touches the real bKash. Every answer is a stand-in, so these
 * prove our side of the conversation: that we ask the right things, believe
 * only bKash's own confirmation, and never act on the same message twice.
 */
class BkashTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected const SECRET = 'super-secret-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
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

    protected function bkash(bool $enabled = true): PaymentMethod
    {
        $method = new PaymentMethod([
            'tenant_id' => $this->store->id,
            'gateway' => 'bkash',
            'is_enabled' => $enabled,
            'settings' => ['username' => 'sandboxTester', 'sandbox' => true],
        ]);

        $method->credentials = [
            'app_key' => '4f6o0cjiki2rfm34kfdadl1eqq',
            'app_secret' => self::SECRET,
            'password' => 'hWD@bcJ',
        ];

        $method->save();

        return $method;
    }

    protected function token(): array
    {
        return [
            'statusCode' => '0000', 'statusMessage' => 'Successful',
            'id_token' => 'eyJhbGciOiJIUzI1NiJ9.stand-in',
            'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => 'refresh-stand-in',
        ];
    }

    protected function created(): array
    {
        return [
            'statusCode' => '0000', 'statusMessage' => 'Successful',
            'paymentID' => 'TR0011abcdef', 'transactionStatus' => 'Initiated',
            'bkashURL' => 'https://sandbox.payment.bkash.com/redirect/TR0011abcdef',
            'amount' => '1000.00', 'currency' => 'BDT', 'intent' => 'sale',
        ];
    }

    protected function executed(): array
    {
        return [
            'statusCode' => '0000', 'statusMessage' => 'Successful',
            'paymentID' => 'TR0011abcdef', 'trxID' => 'AAB12CD34E',
            'transactionStatus' => 'Completed', 'amount' => '1000.00', 'currency' => 'BDT',
            'customerMsisdn' => '01770618567',
        ];
    }

    /**
     * The toast itself. Livewire hands the callback the whole argument list,
     * and a toast is dispatched with one argument.
     *
     * @return array{text: string, tone: string|null}
     */
    protected function toast(array $params): array
    {
        return ['text' => '', 'tone' => null, ...($params[0] ?? [])];
    }

    protected function processor(): PaymentProcessor
    {
        return app(PaymentProcessor::class);
    }

    /*
     * ---------------------------------------------------------------
     * Checking a shop's own credentials
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_check_their_bkash_details_work(): void
    {
        Http::fake(['*/token/grant' => Http::response($this->token())]);

        $this->bkash();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', 'bkash')
            ->assertDispatched('toast', fn ($name, $params) => $this->toast($params)['tone'] === 'ok'
                && str_contains($this->toast($params)['text'], 'accepted your details'));
    }

    public function test_wrong_details_are_explained_in_plain_words(): void
    {
        Http::fake(['*/token/grant' => Http::response([
            'statusCode' => '2001', 'statusMessage' => 'Invalid App Key',
        ])]);

        $this->bkash();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', 'bkash')
            ->assertDispatched('toast', fn ($name, $params) => $this->toast($params)['tone'] === 'bad'
                && str_contains($this->toast($params)['text'], 'app key'));
    }

    public function test_a_shop_cannot_test_details_it_has_not_finished_entering(): void
    {
        Http::fake();

        $method = $this->bkash();
        $method->settings = ['sandbox' => true];   // no username
        $method->save();

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', 'bkash')
            ->assertDispatched('toast', fn ($name, $params) => $this->toast($params)['tone'] === 'bad');

        Http::assertNothingSent();
    }

    /*
     * ---------------------------------------------------------------
     * Taking money
     * ---------------------------------------------------------------
     */

    public function test_starting_a_payment_sends_the_customer_to_bkash(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/create' => Http::response($this->created()),
        ]);

        $result = $this->processor()->start(
            $this->bkash(),
            new Money(100000, 'BDT', 2),
            'https://shop.test/payments/bkash/callback',
        );

        $this->assertSame('https://sandbox.payment.bkash.com/redirect/TR0011abcdef', $result['redirect_url']);
        $this->assertSame(Payment::STATUS_INITIATED, $result['payment']->status);
        $this->assertSame('TR0011abcdef', $result['payment']->gateway_payment_id);
        $this->assertSame(100000, $result['payment']->amount_minor);

        // The amount bKash was told must be the same money, written its way.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/create')
            && $r['amount'] === '1000.00'
            && $r['currency'] === 'BDT'
            && $r['callbackURL'] === 'https://shop.test/payments/bkash/callback');
    }

    public function test_an_approved_payment_is_taken_and_recorded(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/execute' => Http::response($this->executed()),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertTrue($settled->isPaid());
        $this->assertSame('AAB12CD34E', $settled->gateway_transaction_id);
        $this->assertSame('01770618567', $settled->payer_account);
        $this->assertNotNull($settled->paid_at);

        // Rule seven: the follow-up work is written with the payment, not left
        // to a queue that could lose it.
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_the_same_callback_arriving_twice_takes_the_money_once(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/execute' => Http::response($this->executed()),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $this->processor()->settle($payment, $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');

        $executes = collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/execute'))
            ->count();

        $this->assertSame(1, $executes, 'bKash was asked to take the money more than once.');
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'execute')->count());
    }

    public function test_a_cancelled_payment_charges_nothing(): void
    {
        Http::fake();

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $settled = $this->processor()->settle($payment, $method, 'cancel');

        $this->assertSame(Payment::STATUS_CANCELLED, $settled->status);
        $this->assertNull($settled->paid_at);
        $this->assertSame(0, OutboxEvent::count());
        Http::assertNothingSent();
    }

    public function test_bkash_saying_anything_other_than_completed_is_not_a_payment(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/execute' => Http::response([
                'statusCode' => '0000', 'statusMessage' => 'Successful',
                'paymentID' => 'TR0011abcdef', 'transactionStatus' => 'Initiated',
            ]),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertSame(0, OutboxEvent::count());
    }

    public function test_a_payment_lost_on_the_way_back_is_found_by_asking_bkash(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/payment/status' => Http::response($this->executed()),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $found = $this->processor()->reconcile($payment, $method);

        $this->assertTrue($found->isPaid());
        $this->assertSame('AAB12CD34E', $found->gateway_transaction_id);
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    /*
     * ---------------------------------------------------------------
     * The rules that must not bend
     * ---------------------------------------------------------------
     */

    public function test_bkash_refuses_money_that_is_not_taka(): void
    {
        Http::fake(['*/token/grant' => Http::response($this->token())]);

        $this->expectException(GatewayFailed::class);
        $this->expectExceptionMessage('Bangladeshi Taka');

        $this->processor()->start($this->bkash(), new Money(5000, 'MYR', 2), 'https://shop.test/back');
    }

    public function test_a_gateway_that_is_switched_off_cannot_take_money(): void
    {
        Http::fake();

        $this->expectException(GatewayFailed::class);

        $this->processor()->start($this->bkash(enabled: false), new Money(100000, 'BDT', 2), 'https://shop.test/back');

        Http::assertNothingSent();
    }

    public function test_secrets_never_reach_a_message_a_person_could_read(): void
    {
        Http::fake(['*/token/grant' => Http::response([
            'statusCode' => '2001', 'statusMessage' => 'Invalid App Key',
        ])]);

        try {
            app(GatewayFactory::class)->for($this->bkash())->testConnection();
            $this->fail('Expected bKash to refuse those details.');
        } catch (GatewayFailed $e) {
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, (string) $e);
        }
    }

    public function test_the_saved_record_of_a_payment_holds_no_credentials(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/execute' => Http::response($this->executed() + [
                'id_token' => 'leaked', 'app_secret' => self::SECRET,
            ]),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertStringNotContainsString(self::SECRET, json_encode($settled->meta));
        $this->assertStringNotContainsString('leaked', json_encode($settled->meta));
        $this->assertArrayNotHasKey('credentials', $method->fresh()->toArray());
    }

    public function test_one_shop_cannot_settle_another_shops_payment(): void
    {
        Http::fake();

        $other = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        $theirs = Tenancy::run($other, fn () => Payment::factory()->initiated('TR-theirs')->create([
            'tenant_id' => $other->id,
        ]));

        // Standing in our own shop, their payment simply is not there.
        $this->assertNull(Payment::where('gateway_payment_id', 'TR-theirs')->first());
        $this->assertNull(Payment::find($theirs->id));
    }

    /*
     * ---------------------------------------------------------------
     * Giving money back
     * ---------------------------------------------------------------
     */

    public function test_a_refund_is_a_new_record_and_leaves_the_payment_alone(): void
    {
        Http::fake([
            '*/token/grant' => Http::response($this->token()),
            '*/payment/refund' => Http::response([
                'statusCode' => '0000', 'statusMessage' => 'Successful',
                'refundTrxID' => 'REF98XY', 'transactionStatus' => 'Completed',
                'amount' => '400.00', 'currency' => 'BDT',
            ]),
        ]);

        $method = $this->bkash();
        $payment = Payment::factory()->paid()->create(['tenant_id' => $this->store->id]);

        $refund = $this->processor()->refund($payment, $method, new Money(40000, 'BDT', 2), 'Damaged');

        $this->assertSame('REF98XY', $refund->gateway_refund_id);
        $this->assertSame(40000, $refund->amount_minor);

        // Rule five: what was taken still says what was taken.
        $payment->refresh();
        $this->assertSame(100000, $payment->amount_minor);
        $this->assertTrue($payment->isPaid());
        $this->assertSame(60000, $payment->refundableAmount()->minor);
        $this->assertSame(1, OutboxEvent::where('type', 'payment.refunded')->count());
    }

    public function test_a_shop_cannot_refund_more_than_it_took(): void
    {
        Http::fake(['*/token/grant' => Http::response($this->token())]);

        $method = $this->bkash();
        $payment = Payment::factory()->paid()->create(['tenant_id' => $this->store->id]);

        $this->expectException(GatewayFailed::class);
        $this->expectExceptionMessage('more than is left');

        $this->processor()->refund($payment, $method, new Money(150000, 'BDT', 2));
    }
}
