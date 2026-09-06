<?php

namespace Tests\Feature\Payments;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Livewire\Super\GatewayMatrix;
use App\Livewire\Super\ShopPayments;
use App\Models\Admin;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantGatewayGrant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Payments\GatewayCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing([
            'products' => 50, 'online_payments' => true, 'cash_on_delivery' => true,
        ])->create());

        Entitlements::forget();
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function asShopkeeper(): void
    {
        Tenancy::set($this->store);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function asStaff(): void
    {
        Tenancy::forget();
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    // ---- what a shop is offered -------------------------------------------

    public function test_a_shop_is_offered_the_gateways_allowed_in_its_country(): void
    {
        $offered = app(GatewayCatalogue::class)->availableFor($this->store)->keys()->all();

        $this->assertContains('cod', $offered);
        $this->assertContains('bkash', $offered);
        $this->assertContains('self_mfs', $offered);
        $this->assertNotContains('stripe', $offered, 'Stripe is not offered in Bangladesh by default.');
    }

    public function test_staff_can_stop_a_gateway_in_a_country_and_every_shop_there_loses_it(): void
    {
        $this->asStaff();

        Livewire::test(GatewayMatrix::class)->call('toggle', 'bkash', 'BD')->assertSee('not allowed in Bangladesh');

        $this->assertFalse(app(GatewayCatalogue::class)->isAllowedIn('bkash', 'BD'));
        $this->assertNotContains('bkash', app(GatewayCatalogue::class)->availableFor($this->store)->keys()->all());

        // And back again.
        Livewire::test(GatewayMatrix::class)->call('toggle', 'bkash', 'BD');
        $this->assertTrue(app(GatewayCatalogue::class)->isAllowedIn('bkash', 'BD'));
    }

    public function test_staff_can_hand_one_shop_a_gateway_its_country_does_not_get(): void
    {
        $this->asStaff();

        Livewire::test(ShopPayments::class, ['tenant' => $this->store])
            ->call('toggleGrant', 'stripe')
            ->assertSee('handed to');

        $offered = app(GatewayCatalogue::class)->availableFor($this->store);

        $this->assertTrue($offered->has('stripe'));
        $this->assertSame('granted', $offered->get('stripe')['reason']);

        // Only this shop: another Bangladeshi shop still does not get it.
        $neighbour = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        $this->assertFalse(app(GatewayCatalogue::class)->availableFor($neighbour)->has('stripe'));
    }

    public function test_taking_a_grant_back_switches_off_what_the_shop_set_up(): void
    {
        $this->asStaff();
        Livewire::test(ShopPayments::class, ['tenant' => $this->store])->call('toggleGrant', 'stripe');

        Tenancy::run($this->store, fn () => PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'stripe', 'is_enabled' => true,
            'credentials' => ['secret_key' => 'sk_live_abc', 'webhook_secret' => 'whsec_x'],
            'settings' => ['publishable_key' => 'pk_live_1'],
        ]));

        Livewire::test(ShopPayments::class, ['tenant' => $this->store])->call('toggleGrant', 'stripe');

        Tenancy::run($this->store, fn () => $this->assertFalse(PaymentMethod::where('gateway', 'stripe')->firstOrFail()->is_enabled));
    }

    // ---- a shop's own account ---------------------------------------------

    public function test_a_shopkeeper_enters_their_own_details_and_secrets_are_encrypted(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'amarpay')
            ->set('form.store_id', 'dhaka_fashion')
            ->set('form.signature_key', 'sig-1234567890-wxyz')
            ->set('form.sandbox', true)
            ->call('save')
            ->assertDispatched('toast');

        $method = PaymentMethod::where('gateway', 'amarpay')->firstOrFail();

        $this->assertSame('dhaka_fashion', $method->settings['store_id']);
        $this->assertTrue($method->settings['sandbox']);
        $this->assertSame('sig-1234567890-wxyz', $method->credentials['signature_key']);
        $this->assertTrue($method->isComplete());

        // On disk, the secret is scrambled and lives nowhere else.
        $row = DB::table('payment_methods')->where('gateway', 'amarpay')->first();
        $this->assertStringNotContainsString('wxyz', (string) $row->credentials);
        $this->assertStringNotContainsString('wxyz', (string) $row->settings);
    }

    public function test_a_secret_is_shown_back_as_its_last_four_only(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'amarpay')
            ->set('form.store_id', 'dhaka_fashion')
            ->set('form.signature_key', 'sig-1234567890-wxyz')
            ->call('save');

        $component = Livewire::test(PaymentMethodsIndex::class);

        $component->assertSee('••••••••wxyz');
        $this->assertStringNotContainsString('sig-1234567890', $component->html());

        // Opening the form again does not load the secret into it.
        $component->call('edit', 'amarpay')->assertSet('form.signature_key', '');
        $this->assertStringNotContainsString('sig-1234567890', $component->html());
    }

    public function test_leaving_a_secret_blank_keeps_it_and_typing_one_replaces_it(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'amarpay')
            ->set('form.store_id', 'dhaka_fashion')
            ->set('form.signature_key', 'first-key')
            ->call('save');

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'amarpay')
            ->set('form.store_id', 'renamed')
            ->set('form.signature_key', '')
            ->call('save');

        $method = PaymentMethod::where('gateway', 'amarpay')->firstOrFail();
        $this->assertSame('renamed', $method->settings['store_id']);
        $this->assertSame('first-key', $method->credentials['signature_key']);

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'amarpay')
            ->set('form.signature_key', 'second-key')
            ->call('save');

        $this->assertSame('second-key', PaymentMethod::where('gateway', 'amarpay')->firstOrFail()->credentials['signature_key']);
    }

    public function test_secrets_never_leak_through_the_models_array_form(): void
    {
        $this->asShopkeeper();

        $method = PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'bkash',
            'credentials' => ['app_secret' => 'very-secret'], 'settings' => ['username' => 'u'],
        ]);

        $this->assertArrayNotHasKey('credentials', $method->toArray());
        $this->assertStringNotContainsString('very-secret', $method->toJson());
    }

    // ---- switching on ------------------------------------------------------

    public function test_a_gateway_cannot_be_switched_on_until_its_details_are_complete(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'bkash')
            ->set('form.username', 'shop')
            ->call('save');

        Livewire::test(PaymentMethodsIndex::class)->call('toggle', 'bkash')->assertDispatched('toast');

        $this->assertFalse(PaymentMethod::where('gateway', 'bkash')->firstOrFail()->is_enabled);
    }

    public function test_cash_on_delivery_needs_nothing_but_a_switch(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'cod')
            ->set('form.instructions', 'Please have the exact amount ready.')
            ->call('save');

        Livewire::test(PaymentMethodsIndex::class)->call('toggle', 'cod');

        $this->assertTrue(PaymentMethod::where('gateway', 'cod')->firstOrFail()->isReady());
    }

    public function test_mobile_money_to_your_own_number_is_set_up_with_a_number_and_instructions(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->call('edit', 'self_mfs')
            ->set('form.provider', 'bkash')
            ->set('form.account_number', '01711000000')
            ->set('form.account_type', 'personal')
            ->set('form.instructions', 'Send Money to this number, then enter the transaction ID.')
            ->call('save');

        Livewire::test(PaymentMethodsIndex::class)->call('toggle', 'self_mfs');

        $method = PaymentMethod::where('gateway', 'self_mfs')->firstOrFail();

        $this->assertTrue($method->isReady());
        $this->assertSame('01711000000', $method->settings['account_number']);
        $this->assertNull($method->credentials, 'A personal number is not a secret; nothing to encrypt.');
    }

    public function test_a_plan_without_online_payments_cannot_switch_an_online_gateway_on(): void
    {
        $basic = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        app(SubscribeToPackage::class)->handle($basic, Package::factory()->allowing([
            'online_payments' => false, 'cash_on_delivery' => true,
        ])->create());
        Entitlements::forget();

        Tenancy::set($basic);
        $this->actingAs(User::factory()->create(['tenant_id' => $basic->id]));

        Livewire::test(PaymentMethodsIndex::class)
            ->assertSee('Not in your plan')
            ->call('edit', 'amarpay')
            ->set('form.store_id', 'x')
            ->set('form.signature_key', 'y')
            ->call('save');

        Livewire::test(PaymentMethodsIndex::class)->call('toggle', 'amarpay')->assertDispatched('toast');

        $this->assertFalse(PaymentMethod::where('gateway', 'amarpay')->firstOrFail()->is_enabled);
    }

    public function test_a_shop_cannot_set_up_a_gateway_it_is_not_offered(): void
    {
        $this->asShopkeeper();

        Livewire::test(PaymentMethodsIndex::class)
            ->assertDontSee('Stripe')
            ->call('edit', 'stripe')
            ->assertSet('editing', null);
    }

    public function test_one_shop_never_sees_another_shops_payment_details(): void
    {
        $this->asShopkeeper();

        PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'amarpay',
            'credentials' => ['signature_key' => 'mine-only'], 'settings' => ['store_id' => 'mine'],
        ]);

        $other = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['online_payments' => true])->create());

        Tenancy::run($other, function () use ($other) {
            $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));

            $this->assertSame(0, PaymentMethod::count());
            $this->assertSame(0, TenantGatewayGrant::count());
            $this->assertStringNotContainsString('mine', Livewire::test(PaymentMethodsIndex::class)->html());
        });
    }
}
