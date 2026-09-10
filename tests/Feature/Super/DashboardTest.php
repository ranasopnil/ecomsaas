<?php

namespace Tests\Feature\Super;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Super\Dashboard;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Payments;
use App\Services\Billing\SubscribeToPackage;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The first screen platform staff see.
 *
 * Every figure on it is counted from real rows, so these prove the counting:
 * that shops are counted by the state they are in, that money is never added
 * up across currencies, that a payment nobody has confirmed is not counted as
 * taken, and that a platform with nothing on it says so plainly.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = Admin::factory()->create(['name' => 'Md Jewel Rana']);
        $this->actingAs($this->staff, 'admin');
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    /**
     * A shop on a plan, with an owner who can sign in.
     */
    protected function aShop(string $name, string $status = Tenant::STATUS_ACTIVE, string $currency = 'BDT'): Tenant
    {
        $shop = Tenant::factory()->create([
            'name' => $name,
            'status' => $status,
            'currency' => $currency,
            'currency_exponent' => 2,
            'country_code' => 'BD',
        ]);

        $package = Package::factory()->create(['currency' => $currency, 'price_minor' => 99000, 'trial_days' => 0]);

        app(SubscribeToPackage::class)->handle($shop, $package);

        Tenancy::run($shop, fn () => User::factory()->create([
            'tenant_id' => $shop->id,
            'name' => 'Owner of '.$name,
            'role' => User::ROLE_OWNER,
        ]));

        Tenancy::forget();
        Entitlements::forget();

        return $shop->fresh();
    }

    /**
     * Money a shop says it sent, and staff found.
     */
    protected function aConfirmedPayment(Tenant $shop, int $minor, string $currency = 'BDT'): void
    {
        Tenancy::run($shop, function () use ($shop, $minor, $currency) {
            $payments = app(Payments::class);

            $payment = $payments->claim(
                new Money($minor, $currency, 2),
                SubscriptionPayment::PURPOSE_RENEWAL,
                'bkash',
                'TRX'.$minor,
            );

            $payments->confirm($payment, $shop, $this->staff);
        });

        Tenancy::forget();
        Entitlements::forget();
    }

    /*
     * ---------------------------------------------------------------
     * The eight figures
     * ---------------------------------------------------------------
     */

    public function test_it_counts_shops_by_the_state_they_are_in(): void
    {
        $this->aShop('Dhaka Fashion');
        $this->aShop('Chittagong Mart');
        $this->aShop('Sylhet Store', Tenant::STATUS_SUSPENDED);

        Livewire::test(Dashboard::class)
            ->assertSee('Platform overview')
            ->assertSee('Total shops')
            ->assertSee('Open for business')
            ->assertSee('Suspended')
            // Three shops, two of them open, one suspended.
            ->assertSee('66.7% of every shop')
            ->assertSee('33.3% of every shop');
    }

    public function test_a_platform_with_nothing_on_it_is_told_so_rather_than_shown_a_trend(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('No shops yet')
            ->assertSee('no shops yet')
            ->assertSee('Nothing to draw yet')
            ->assertSee('No payment has been confirmed yet')
            // A first month is not "up 100%".
            ->assertDontSee('↑')
            ->assertDontSee('↓');
    }

    public function test_money_is_only_counted_once_staff_have_confirmed_it(): void
    {
        $shop = $this->aShop('Dhaka Fashion');

        Tenancy::run($shop, fn () => app(Payments::class)->claim(
            new Money(99000, 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash',
            'TRX-WAITING',
        ));

        Tenancy::forget();

        Livewire::test(Dashboard::class)
            // Waiting to be checked, not taken.
            ->assertSee('Waiting to be checked')
            ->assertSee('BDT 990.00')
            ->assertSee('1 payment to look at')
            ->assertSee('No payment has been confirmed yet');
    }

    public function test_confirmed_money_is_counted_and_the_shop_appears_among_those_who_pay(): void
    {
        $shop = $this->aShop('Dhaka Fashion');
        $this->aConfirmedPayment($shop, 99000);

        Livewire::test(Dashboard::class)
            ->assertSee('Money taken')
            ->assertSee('BDT 990.00')
            ->assertSee('The shops that pay most')
            ->assertSee('Dhaka Fashion')
            ->assertDontSee('No payment has been confirmed yet');
    }

    /*
     * ---------------------------------------------------------------
     * Money in more than one currency
     * ---------------------------------------------------------------
     */

    public function test_money_in_two_currencies_is_never_added_together(): void
    {
        $dhaka = $this->aShop('Dhaka Fashion', currency: 'BDT');
        $kl = $this->aShop('KL Threads', currency: 'MYR');

        $this->aConfirmedPayment($dhaka, 99000);          // BDT 990.00
        $this->aConfirmedPayment($kl, 9950, 'MYR');       // MYR 99.50

        Livewire::test(Dashboard::class)
            ->assertSee('BDT 990.00')
            ->assertSee('MYR 99.50')
            // 99000 + 9950 is not a number that means anything.
            ->assertDontSee('1,089.50');
    }

    /*
     * ---------------------------------------------------------------
     * The plans ring and the country list
     * ---------------------------------------------------------------
     */

    public function test_the_ring_puts_every_shop_on_a_plan_or_says_it_has_none(): void
    {
        $this->aShop('Dhaka Fashion');
        Tenant::factory()->create(['name' => 'Nobody Yet', 'country_code' => 'MY']);

        Livewire::test(Dashboard::class)
            ->assertSee('Which plan they are on')
            ->assertSee('No plan')
            ->assertSee('Where they are')
            ->assertSee('Bangladesh')
            ->assertSee('Malaysia');
    }

    /*
     * ---------------------------------------------------------------
     * The tables
     * ---------------------------------------------------------------
     */

    public function test_the_newest_shops_are_listed_with_who_owns_them(): void
    {
        $this->aShop('Dhaka Fashion');

        Livewire::test(Dashboard::class)
            ->assertSee('The newest shops')
            ->assertSee('Dhaka Fashion')
            ->assertSee('Owner of Dhaka Fashion');
    }

    public function test_the_stretch_of_time_can_be_changed_and_nonsense_falls_back(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('window', '30')
            ->call('setWindow', '90')
            ->assertSet('window', '90')
            ->call('setWindow', 'nonsense')
            ->assertSet('window', '30')
            ->assertSet('months', '12')
            ->call('setMonths', '6')
            ->assertSet('months', '6')
            ->call('setMonths', 'nonsense')
            ->assertSet('months', '12');
    }

    /*
     * ---------------------------------------------------------------
     * Reaching across shops is written down
     * ---------------------------------------------------------------
     */

    public function test_reading_the_platform_figures_is_written_to_the_audit_log_once_a_day(): void
    {
        Livewire::test(Dashboard::class)->call('setWindow', '90');

        $looks = AuditLog::where('action', 'platform.viewed')->get();

        $this->assertCount(1, $looks, 'One row a day, not one a click.');
        $this->assertSame($this->staff->id, $looks->first()->admin_id);
    }

    public function test_downloading_the_shop_list_is_written_down_too(): void
    {
        $this->aShop('Dhaka Fashion');

        Livewire::test(Dashboard::class)->call('export');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.exported',
            'admin_id' => $this->staff->id,
        ]);
    }

    /*
     * ---------------------------------------------------------------
     * What is not claimed
     * ---------------------------------------------------------------
     */

    public function test_it_claims_no_uptime_or_support_figures_it_cannot_measure(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('The machine underneath')
            ->assertSee('Only what can actually be measured from this server.')
            // There is no ticket system and no uptime monitor on this platform.
            ->assertDontSee('Support tickets')
            ->assertDontSee('99.9');
    }

    /**
     * The whole page, layout and all, not just the component inside it.
     */
    public function test_the_page_itself_draws_with_the_rail_and_the_bar_round_it(): void
    {
        $this->aShop('Dhaka Fashion');

        $this->get('/super')
            ->assertOk()
            ->assertSee('Platform staff')
            ->assertSee('Super admin')
            ->assertSee('Payments &amp; money due', false)
            ->assertSee('Platform overview')
            ->assertSee('Dhaka Fashion');
    }

    public function test_a_trial_shop_is_counted_as_a_trial_and_not_as_paying(): void
    {
        $shop = Tenant::factory()->create(['name' => 'Just Looking', 'country_code' => 'BD']);

        app(SubscribeToPackage::class)->handle(
            $shop,
            Package::factory()->create(['trial_days' => 14, 'currency' => 'BDT']),
        );

        Entitlements::forget();

        $this->assertSame(
            Subscription::STATUS_TRIALING,
            Subscription::withoutGlobalScopes()->where('tenant_id', $shop->id)->value('status'),
        );

        Livewire::test(Dashboard::class)
            ->assertSee('On a trial')
            ->assertSee('Coming in each month')
            // Nothing recurring is counted from a shop that has not paid yet.
            ->assertSee('—');
    }
}
