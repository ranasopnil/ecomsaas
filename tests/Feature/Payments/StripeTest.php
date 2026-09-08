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
use App\Services\Payments\Gateways\Stripe;
use App\Services\Payments\PaymentProcessor;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stripe, using one shop's own Stripe account.
 *
 * Nothing here touches the real Stripe. Every answer is a stand-in, so these
 * prove our side of the conversation: that we ask for the right amount in the
 * right currency, believe only Stripe's own account of what happened, and
 * never take the same money twice however many times we are told about it.
 */
class StripeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected const SECRET_KEY = 'sk_test_51Nq0AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

    protected const SIGNING_SECRET = 'whsec_stand_in_signing_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'name' => 'Kuala Grocer', 'currency' => 'MYR', 'currency_exponent' => 2, 'country_code' => 'MY',
        ]);

        // Priced in the shop's own currency: a shop cannot be put on a plan
        // that has no price where it trades.
        $package = Package::factory()->allowing([
            'products' => 50, 'online_payments' => true,
        ])->create(['currency' => 'MYR', 'currency_exponent' => 2]);

        app(SubscribeToPackage::class)->handle($this->store, $package);

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function stripe(bool $enabled = true, bool $withWebhookSecret = true): PaymentMethod
    {
        $method = new PaymentMethod([
            'tenant_id' => $this->store->id,
            'gateway' => Stripe::KEY,
            'is_enabled' => $enabled,
            'settings' => [],
        ]);

        $method->credentials = array_filter([
            'secret_key' => self::SECRET_KEY,
            'webhook_secret' => $withWebhookSecret ? self::SIGNING_SECRET : null,
        ]);

        $method->save();

        return $method;
    }

    /**
     * A Checkout Session as Stripe hands it back when it is opened.
     */
    protected function openSession(array $with = []): array
    {
        return [
            'id' => 'cs_test_a1b2c3d4e5',
            'object' => 'checkout.session',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3d4e5',
            'amount_total' => 100000,
            'currency' => 'myr',
            ...$with,
        ];
    }

    /**
     * The same session once it has been paid.
     */
    protected function paidSession(array $with = []): array
    {
        return $this->openSession([
            'status' => 'complete',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_3NqStandIn0001',
            'customer_details' => ['email' => 'shopper@example.com', 'name' => 'A Shopper'],
            ...$with,
        ]);
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

    /**
     * A webhook exactly as Stripe would send it, signed with the shop's own
     * signing secret.
     *
     * @return array{payload: string, header: string}
     */
    protected function webhook(string $type, array $session, string $eventId = 'evt_test_0001', ?string $secret = null, ?int $at = null): array
    {
        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $session],
        ]);

        $at ??= time();
        $signature = hash_hmac('sha256', $at.'.'.$payload, $secret ?? self::SIGNING_SECRET);

        return ['payload' => $payload, 'header' => "t={$at},v1={$signature}"];
    }

    /*
     * ---------------------------------------------------------------
     * Checking a shop's own key
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_check_their_stripe_key_works(): void
    {
        Http::fake(['*/v1/balance' => Http::response(['object' => 'balance', 'livemode' => false])]);

        $this->stripe();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', Stripe::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'ok'
                && str_contains($this->toast($params)['text'], 'test system'));

        // The key travels as a bearer token and nowhere else.
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::SECRET_KEY));
    }

    public function test_a_key_stripe_does_not_accept_is_explained_in_plain_words(): void
    {
        Http::fake(['*/v1/balance' => Http::response([
            'error' => ['type' => 'invalid_request_error', 'message' => 'Invalid API Key provided: sk_test_***'],
        ], 401)]);

        $this->stripe();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', Stripe::KEY)
            ->assertDispatched('toast', fn ($event, $params) => $this->toast($params)['tone'] === 'bad'
                && str_contains($this->toast($params)['text'], 'did not accept that secret key'));
    }

    public function test_a_shop_can_set_stripe_up_with_the_secret_key_alone(): void
    {
        $method = $this->stripe(withWebhookSecret: false);

        // The signing secret matters only once webhooks are added, so a shop
        // is not blocked from starting without it.
        $this->assertTrue($method->isComplete());
    }

    /*
     * ---------------------------------------------------------------
     * Taking a payment
     * ---------------------------------------------------------------
     */

    public function test_starting_a_payment_sends_the_customer_to_stripes_page(): void
    {
        Http::fake(['*/v1/checkout/sessions' => Http::response($this->openSession())]);

        $result = $this->processor()->start(
            $this->stripe(),
            new Money(100000, 'MYR', 2),
            'https://kuala.test/payments/stripe/callback',
        );

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_a1b2c3d4e5', $result['redirect_url']);
        $this->assertSame(Payment::STATUS_INITIATED, $result['payment']->status);
        $this->assertSame('cs_test_a1b2c3d4e5', $result['payment']->gateway_payment_id);

        // A test key means test money. The record has to say so.
        $this->assertTrue($result['payment']->is_sandbox);

        Http::assertSent(function (Request $r) {
            $data = $r->data();

            return str_ends_with($r->url(), '/v1/checkout/sessions')
                && $data['mode'] === 'payment'
                && $data['line_items'][0]['price_data']['currency'] === 'myr'
                && $data['line_items'][0]['price_data']['unit_amount'] === 100000
                && str_contains($data['success_url'], 'outcome=success')
                // Stripe fills this in itself; it has to survive as written.
                && str_contains($data['success_url'], '{CHECKOUT_SESSION_ID}')
                && str_contains($data['cancel_url'], 'outcome=cancel')
                && $r->hasHeader('Idempotency-Key');
        });
    }

    public function test_the_amount_is_sent_the_way_stripe_counts_that_currency(): void
    {
        Http::fake(['*/v1/checkout/sessions' => Http::response($this->openSession())]);

        // Rupiah has no decimals for us — 15,000 Rp is fifteen thousand — but
        // Stripe counts it in hundredths. Sending our own number would ask the
        // customer for a hundredth of the price.
        $this->processor()->start(
            $this->stripe(),
            new Money(15000, 'IDR', 0),
            'https://kuala.test/payments/stripe/callback',
        );

        Http::assertSent(fn (Request $r) => $r->data()['line_items'][0]['price_data']['unit_amount'] === 1500000
            && $r->data()['line_items'][0]['price_data']['currency'] === 'idr');
    }

    public function test_a_paid_session_is_recorded_as_a_payment(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertTrue($settled->isPaid());
        $this->assertSame('pi_3NqStandIn0001', $settled->gateway_transaction_id);
        $this->assertSame('shopper@example.com', $settled->payer_account);
        $this->assertNotNull($settled->paid_at);

        // Rule seven: the follow-up work is written with the payment.
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_coming_back_from_stripe_twice_takes_the_money_once(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $this->processor()->settle($payment, $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');
        $this->processor()->settle($payment->fresh(), $method, 'success');

        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'execute')->count());
    }

    public function test_a_customer_who_backs_out_is_charged_nothing(): void
    {
        Http::fake();

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $settled = $this->processor()->settle($payment, $method, 'cancel');

        $this->assertSame(Payment::STATUS_CANCELLED, $settled->status);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
        Http::assertNothingSent();
    }

    public function test_landing_on_the_success_address_is_not_itself_a_payment(): void
    {
        // The customer's browser says "success"; Stripe says the session was
        // never paid. Stripe wins.
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->openSession(['status' => 'expired']))]);

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertSame(Payment::STATUS_FAILED, $settled->status);
        $this->assertStringContainsString('expired', $settled->failure_reason);
        $this->assertSame(0, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_a_payment_lost_on_the_way_back_is_found_by_asking_stripe(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        // The customer never came back. Asking Stripe finds the money.
        $found = $this->processor()->reconcile($payment, $method);

        $this->assertTrue($found->isPaid());
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    /*
     * ---------------------------------------------------------------
     * Coming back from Stripe's page
     * ---------------------------------------------------------------
     */

    public function test_a_customer_coming_back_from_a_paid_page_is_told_so(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $this->stripe();
        Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $url = $this->shopUrl();
        Tenancy::forget();

        $this->get($url.'/payments/stripe/callback?outcome=success&session_id=cs_test_a1b2c3d4e5')
            ->assertOk()
            ->assertSee('Payment received');

        Tenancy::set($this->store);
        $this->assertTrue(Payment::first()->isPaid());
    }

    public function test_a_customer_who_pressed_back_on_stripes_page_is_charged_nothing(): void
    {
        Http::fake();

        $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $url = $this->shopUrl();
        $reference = $payment->reference;
        Tenancy::forget();

        // Stripe adds nothing to the cancel address, so our own reference is
        // what finds the payment again.
        $this->get($url.'/payments/stripe/callback?outcome=cancel&reference='.$reference)->assertOk();

        Tenancy::set($this->store);
        $this->assertSame(Payment::STATUS_CANCELLED, Payment::first()->status);
        Http::assertNothingSent();
    }

    public function test_the_payments_screen_shows_stripe_with_its_own_mark(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->assertSee('Stripe')
            // The mark is drawn here, not fetched from Stripe.
            ->assertSee('#635BFF', false)
            ->assertSee('Cash on delivery');
    }

    public function test_the_form_tells_the_shopkeeper_where_stripe_should_send_its_messages(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', Stripe::KEY)
            ->assertSee('/payments/stripe/webhook', false)
            ->assertSee('signing secret');
    }

    /*
     * ---------------------------------------------------------------
     * What Stripe tells the shop directly
     * ---------------------------------------------------------------
     */

    public function test_a_signed_webhook_finishes_the_payment(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $this->stripe();
        Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $signed = $this->webhook('checkout.session.completed', $this->paidSession());
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->call('POST', $url.'/payments/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signed['header'],
            'CONTENT_TYPE' => 'application/json',
        ], $signed['payload'])->assertOk();

        Tenancy::set($this->store);

        $this->assertTrue(Payment::first()->isPaid());
        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
    }

    public function test_an_unsigned_or_forged_webhook_changes_nothing(): void
    {
        Http::fake();

        $this->stripe();
        Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $forged = $this->webhook('checkout.session.completed', $this->paidSession(), secret: 'whsec_not_the_real_one');
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->call('POST', $url.'/payments/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $forged['header'],
            'CONTENT_TYPE' => 'application/json',
        ], $forged['payload'])->assertStatus(400);

        // And with no signature at all.
        $this->call('POST', $url.'/payments/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $forged['payload'])->assertStatus(400);

        Tenancy::set($this->store);

        $this->assertFalse(Payment::first()->isPaid());
        Http::assertNothingSent();
    }

    public function test_an_old_message_cannot_be_replayed_later(): void
    {
        $stale = $this->webhook('checkout.session.completed', $this->paidSession(), at: time() - 3600);

        $this->assertFalse(Stripe::signatureIsValid($stale['payload'], $stale['header'], self::SIGNING_SECRET));
    }

    public function test_the_same_webhook_arriving_twice_takes_the_money_once(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession())]);

        $this->stripe();
        Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $signed = $this->webhook('checkout.session.completed', $this->paidSession());
        $url = $this->shopUrl();
        Tenancy::forget();

        foreach (range(1, 3) as $ignored) {
            $this->call('POST', $url.'/payments/stripe/webhook', [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $signed['header'],
                'CONTENT_TYPE' => 'application/json',
            ], $signed['payload'])->assertOk();
        }

        Tenancy::set($this->store);

        $this->assertSame(1, OutboxEvent::where('type', 'payment.completed')->count());
        $this->assertSame(1, PaymentEvent::where('type', 'checkout.session.completed')->count());
    }

    public function test_a_shop_with_no_signing_secret_takes_no_webhooks(): void
    {
        Http::fake();

        $this->stripe(withWebhookSecret: false);
        Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $signed = $this->webhook('checkout.session.completed', $this->paidSession());
        $url = $this->shopUrl();
        Tenancy::forget();

        $this->call('POST', $url.'/payments/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signed['header'],
            'CONTENT_TYPE' => 'application/json',
        ], $signed['payload'])->assertNotFound();
    }

    /*
     * ---------------------------------------------------------------
     * Giving money back
     * ---------------------------------------------------------------
     */

    public function test_a_refund_is_a_new_record_and_leaves_the_payment_alone(): void
    {
        Http::fake(['*/v1/refunds' => Http::response([
            'id' => 're_3NqStandIn0001', 'object' => 'refund', 'amount' => 40000, 'status' => 'succeeded',
        ])]);

        $method = $this->stripe();
        $payment = Payment::factory()->paid('pi_3NqStandIn0001')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY, 'currency' => 'MYR',
        ]);

        $refund = $this->processor()->refund($payment, $method, new Money(40000, 'MYR', 2), 'Sent the wrong size');

        $this->assertSame(PaymentRefund::STATUS_COMPLETED, $refund->status);
        $this->assertSame('re_3NqStandIn0001', $refund->gateway_refund_id);

        // Rule five: the payment itself is untouched.
        $this->assertSame(100000, $payment->fresh()->amount_minor);
        $this->assertTrue($payment->fresh()->isPaid());
        $this->assertSame(60000, $payment->fresh()->refundableAmount()->minor);

        Http::assertSent(fn (Request $r) => $r['payment_intent'] === 'pi_3NqStandIn0001' && (int) $r['amount'] === 40000);
    }

    /*
     * ---------------------------------------------------------------
     * Keeping the shop's key to itself
     * ---------------------------------------------------------------
     */

    public function test_the_secret_key_never_reaches_a_message_a_person_could_read(): void
    {
        Http::fake(['*/v1/balance' => Http::response([
            'error' => ['message' => 'Invalid API Key provided: '.self::SECRET_KEY],
        ], 401)]);

        $this->stripe();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->call('test', Stripe::KEY)
            ->assertDispatched('toast', fn ($event, $params) => ! str_contains($this->toast($params)['text'], self::SECRET_KEY));
    }

    public function test_the_saved_record_of_a_payment_holds_no_credentials(): void
    {
        Http::fake(['*/v1/checkout/sessions/*' => Http::response($this->paidSession([
            'client_secret' => 'cs_test_secret_should_not_be_kept',
        ]))]);

        $method = $this->stripe();
        $payment = Payment::factory()->initiated('cs_test_a1b2c3d4e5')->create([
            'tenant_id' => $this->store->id, 'gateway' => Stripe::KEY,
        ]);

        $settled = $this->processor()->settle($payment, $method, 'success');

        $this->assertArrayNotHasKey('client_secret', $settled->meta ?? []);
        $this->assertStringNotContainsString(self::SECRET_KEY, json_encode($settled->toArray()));
    }

    public function test_a_live_key_is_never_shown_as_a_test_one(): void
    {
        $method = $this->stripe();

        $method->putSecret('secret_key', 'sk_live_51NqRealMoney0123456789');
        $method->save();

        $this->assertFalse(app(\App\Services\Payments\GatewayFactory::class)->for($method)->isTestMode());
    }
}
