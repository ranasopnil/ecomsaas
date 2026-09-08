<?php

namespace Tests\Feature\Billing;

use App\Exceptions\PlanChangeRefused;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PlanIndex;
use App\Livewire\Super\AddonIndex;
use App\Livewire\Super\BillingIndex;
use App\Models\Addon;
use App\Models\AddonPrice;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Concerns\TenantScope;
use App\Models\Domain;
use App\Models\LedgerEntry;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Payments;
use App\Services\Billing\PlanChange;
use App\Services\Billing\Renewals;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paying for the platform: renewals, moving between plans, and extras.
 *
 * Two rules run through all of it. Money never moves backwards — a shop that
 * moves down waits for its renewal date rather than being given anything
 * back. And a shop that has not paid loses its own dashboard, never its
 * storefront: its customers had no part in a billing problem.
 */
class PlanAndAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected Package $starter;

    protected Package $growth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        $this->starter = $this->plan('Starter', '990', [
            'products' => 50, 'staff_accounts' => 1, 'custom_domains' => 0,
            'storage_mb' => 500, 'orders_per_month' => 200,
            'cash_on_delivery' => true, 'online_payments' => false,
            'courier_pickup' => false, 'discount_codes' => false,
        ]);

        $this->growth = $this->plan('Growth', '2490', [
            'products' => 1000, 'staff_accounts' => 3, 'custom_domains' => 1,
            'storage_mb' => 5000, 'orders_per_month' => 2000,
            'cash_on_delivery' => true, 'online_payments' => true,
            'courier_pickup' => true, 'discount_codes' => true,
        ]);

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    /**
     * @param  array<string, int|bool>  $allows
     */
    protected function plan(string $name, string $price, array $allows): Package
    {
        $package = Package::factory()->allowing($allows)->create([
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'price_minor' => Money::fromDecimal($price, 'BDT', 2)->minor,
            'trial_days' => 14,
        ]);

        return $package->fresh(['prices', 'entitlements']);
    }

    protected function subscribe(Package $package): Subscription
    {
        // Started sixteen days ago, so the month has a fortnight left in it.
        // The start date itself is set here rather than edited afterwards:
        // what a shop agreed to is not something this test may rewrite.
        $subscription = app(SubscribeToPackage::class)->handle($this->store, $package, now()->subDays(16));

        Entitlements::forget();

        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'current_period_ends_at' => now()->addDays(14),
        ])->save();

        return $subscription->refresh();
    }

    protected function sellSomething(int $howMany = 1): void
    {
        foreach (range(1, $howMany) as $n) {
            app(ProductService::class)->create([
                'name' => 'Thing '.uniqid(), 'regular_price' => '100', 'stock' => 1,
                'status' => Product::STATUS_ACTIVE,
            ]);
        }
    }

    protected function anAddon(string $name, string $kind, string $feature, ?int $units, string $price): Addon
    {
        $addon = Addon::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'kind' => $kind,
            'feature' => $feature,
            'unit_amount' => $units,
            'is_active' => true,
        ]);

        AddonPrice::create([
            'addon_id' => $addon->id,
            'currency' => 'BDT',
            'currency_exponent' => 2,
            'price_minor' => Money::fromDecimal($price, 'BDT', 2)->minor,
        ]);

        return $addon->fresh('prices');
    }

    /*
     * ---------------------------------------------------------------
     * Seeing the plan
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_see_what_they_are_on_and_what_they_have_used(): void
    {
        $this->subscribe($this->starter);
        $this->sellSomething(3);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PlanIndex::class)
            ->assertSee('Starter')
            ->assertSee('990.00')
            ->assertSee('Products')
            // Three of the fifty the plan allows.
            ->assertSee('of 50')
            ->assertSee('Growth');
    }

    /*
     * ---------------------------------------------------------------
     * Moving up and down
     * ---------------------------------------------------------------
     */

    public function test_moving_up_costs_only_the_difference_for_the_days_that_are_left(): void
    {
        $subscription = $this->subscribe($this->starter);

        // 14 days left of a 30-day month. Growth costs 2,490 and Starter 990,
        // so the difference for those days is 1,500 × 14 ÷ 30 = 700.
        $difference = app(PlanChange::class)->differenceToday($subscription, $this->growth, $this->store);

        $this->assertSame(70000, $difference->minor);
        $this->assertSame('BDT', $difference->currency);
    }

    public function test_moving_up_starts_when_the_money_is_found_and_not_before(): void
    {
        $subscription = $this->subscribe($this->starter);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PlanIndex::class)
            ->call('look', $this->growth->id)
            ->call('moveUp', $this->growth->id)
            ->assertHasNoErrors();

        // Asked for, not given: the shop is still on Starter.
        $this->assertSame($this->starter->id, Subscription::query()->active()->first()->package_id);
        $this->assertSame(50, Entitlements::limit('products'));

        $payment = SubscriptionPayment::query()->firstOrFail();
        $this->assertSame(SubscriptionPayment::PURPOSE_UPGRADE, $payment->purpose);
        $this->assertSame(70000, $payment->amount_minor);

        // Staff find the money.
        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);
        Entitlements::forget();

        $this->assertSame($this->growth->id, Subscription::query()->active()->first()->package_id);
        $this->assertSame(1000, Entitlements::limit('products'));
    }

    public function test_moving_down_waits_for_the_renewal_date(): void
    {
        $subscription = $this->subscribe($this->growth);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));

        Livewire::test(PlanIndex::class)
            ->call('look', $this->starter->id)
            ->call('moveDown', $this->starter->id)
            ->assertHasNoErrors();

        $subscription->refresh();

        // Booked, not done. The shop keeps what it paid for.
        $this->assertSame($this->starter->id, $subscription->scheduled_package_id);
        $this->assertSame($this->growth->id, $subscription->package_id);
        $this->assertSame(1000, Entitlements::limit('products'));

        // And it can change its mind.
        Livewire::test(PlanIndex::class)->call('keepMyPlan');
        $this->assertNull($subscription->fresh()->scheduled_package_id);
    }

    public function test_a_shop_bigger_than_the_smaller_plan_is_told_what_is_over(): void
    {
        $this->subscribe($this->growth);

        // Growth allows one web address of its own; Starter allows none.
        Domain::create([
            'tenant_id' => $this->store->id,
            'hostname' => 'dhakashop.example.com',
            'type' => Domain::TYPE_CUSTOM,
            'status' => Domain::STATUS_VERIFIED,
        ]);

        $subscription = Subscription::query()->active()->firstOrFail();

        try {
            app(PlanChange::class)->scheduleDowngrade($subscription, $this->starter, $this->store);
            $this->fail('The move down should have been refused.');
        } catch (PlanChangeRefused $e) {
            $this->assertStringContainsString('bigger than Starter allows', $e->getMessage());
            $this->assertStringContainsString('custom domains: you have 1, Starter allows 0', $e->getMessage());
        }

        // Nothing of the shop's own was touched to make it fit.
        $this->assertSame(1, Domain::where('type', Domain::TYPE_CUSTOM)->count());
        $this->assertNull($subscription->fresh()->scheduled_package_id);
    }

    public function test_a_booked_move_down_happens_on_the_day(): void
    {
        $subscription = $this->subscribe($this->growth);
        app(PlanChange::class)->scheduleDowngrade($subscription, $this->starter, $this->store);

        // The month runs out.
        $subscription->forceFill(['current_period_ends_at' => now()->subMinute()])->save();

        Tenancy::forget();
        app(Renewals::class)->tick();
        Tenancy::set($this->store);
        Entitlements::forget();

        $now = Subscription::query()->active()->firstOrFail();

        $this->assertSame($this->starter->id, $now->package_id);
        $this->assertSame(50, Entitlements::limit('products'));
    }

    /*
     * ---------------------------------------------------------------
     * The clock
     * ---------------------------------------------------------------
     */

    public function test_a_month_that_runs_out_falls_due_but_changes_nothing_else(): void
    {
        $subscription = $this->subscribe($this->starter);
        $subscription->forceFill(['current_period_ends_at' => now()->subDay()])->save();

        Tenancy::forget();
        app(Renewals::class)->tick();
        Tenancy::set($this->store);

        $subscription->refresh();

        $this->assertTrue($subscription->isPastDue());
        $this->assertTrue($subscription->isInGrace());
        $this->assertFalse($subscription->isLocked());

        // Still allowed to trade: the shop has seven days.
        $this->assertSame(50, Entitlements::limit('products'));
        $this->assertSame(1, OutboxEvent::where('type', 'subscription.past_due')->count());
    }

    public function test_after_the_days_of_grace_the_dashboard_closes_and_the_shop_stays_open(): void
    {
        $subscription = $this->subscribe($this->starter);
        $subscription->forceFill([
            'status' => Subscription::STATUS_PAST_DUE,
            'current_period_ends_at' => now()->subDays(8),
            'grace_ends_at' => now()->subDay(),
        ])->save();

        Tenancy::forget();
        app(Renewals::class)->tick();
        Tenancy::set($this->store);
        Entitlements::forget();

        $subscription->refresh();

        $this->assertTrue($subscription->isLocked());

        // The storefront is untouched. The shop can still take money and
        // still sell what it has: its customers did nothing wrong.
        $this->assertTrue($subscription->isActive());
        $this->assertTrue(Entitlements::allows('cash_on_delivery'));
        $this->assertSame(50, Entitlements::limit('products'));
    }

    public function test_paying_opens_everything_again_and_moves_the_date(): void
    {
        $subscription = $this->subscribe($this->starter);
        $subscription->forceFill([
            'status' => Subscription::STATUS_PAST_DUE,
            'current_period_ends_at' => now()->subDays(9),
            'grace_ends_at' => now()->subDays(2),
            'locked_at' => now()->subDay(),
        ])->save();

        $payment = app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash',
            '8N7A2K9QX1',
            null,
            [],
            $subscription,
        );

        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create(), 'Found it');

        Tenancy::set($this->store);
        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertFalse($subscription->isLocked());
        $this->assertNull($subscription->grace_ends_at);

        // A shop that pays late is not charged for the days it spent chasing
        // the receipt: the new month runs from today.
        $this->assertTrue($subscription->current_period_ends_at->isAfter(now()->addDays(27)));

        $this->assertTrue($payment->fresh()->isConfirmed());
        $this->assertNotNull($payment->fresh()->covers_to);
    }

    public function test_confirming_a_payment_is_written_into_the_shops_own_book(): void
    {
        $subscription = $this->subscribe($this->starter);

        $payment = app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash', 'REF1', null, [], $subscription,
        );

        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);

        $entry = LedgerEntry::where('kind', LedgerEntry::KIND_EXPENSE)->firstOrFail();

        $this->assertSame(99000, $entry->amount_minor);
        $this->assertSame(LedgerEntry::OUT, $entry->direction);
        $this->assertStringContainsString('plan', mb_strtolower($entry->description));
    }

    public function test_a_payment_confirmed_twice_is_only_acted_on_once(): void
    {
        $subscription = $this->subscribe($this->starter);

        $payment = app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash', 'REF1', null, [], $subscription,
        );

        $admin = Admin::factory()->create();
        app(Payments::class)->confirm($payment, $this->store, $admin);
        $endsAt = Subscription::query()->active()->first()->current_period_ends_at;

        app(Payments::class)->confirm($payment->fresh(), $this->store, $admin);

        Tenancy::set($this->store);

        $this->assertSame(1, LedgerEntry::where('kind', LedgerEntry::KIND_EXPENSE)->count());
        $this->assertTrue($endsAt->equalTo(Subscription::query()->active()->first()->current_period_ends_at));
    }

    public function test_staff_reaching_across_shops_is_written_down(): void
    {
        $subscription = $this->subscribe($this->starter);

        $payment = app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash', 'REF1', null, [], $subscription,
        );

        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create(['name' => 'Platform Staff']));

        $logged = AuditLog::where('action', 'subscription_payment.confirmed')->firstOrFail();

        $this->assertSame($this->store->id, $logged->tenant_id);
        $this->assertSame($this->store->name, $logged->tenant_name);
        $this->assertSame(99000, $logged->meta['amount_minor']);
    }

    /*
     * ---------------------------------------------------------------
     * Add-ons
     * ---------------------------------------------------------------
     */

    public function test_an_extra_stacks_on_top_of_the_plan(): void
    {
        $this->subscribe($this->starter);
        $addon = $this->anAddon('50 more products', Addon::KIND_UNITS, 'products', 50, '300');

        $this->assertSame(50, Entitlements::limit('products'));

        // Bought, but not yet paid for: it grants nothing.
        $payment = app(Payments::class)->requestAddon($addon, 2, $this->store);

        Entitlements::forget();
        $this->assertSame(50, Entitlements::limit('products'));
        $this->assertSame(60000, $payment->amount_minor);

        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);
        Entitlements::forget();

        // The plan's fifty, plus fifty, plus fifty.
        $this->assertSame(150, Entitlements::limit('products'));
    }

    public function test_an_extra_can_switch_on_something_the_plan_does_not_include(): void
    {
        $this->subscribe($this->starter);
        $addon = $this->anAddon('Discount codes', Addon::KIND_SWITCH, 'discount_codes', null, '400');

        $this->assertFalse(Entitlements::allows('discount_codes'));

        $payment = app(Payments::class)->requestAddon($addon, 1, $this->store);
        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);
        Entitlements::forget();

        $this->assertTrue(Entitlements::allows('discount_codes'));
    }

    public function test_what_a_shop_owes_counts_its_extras_too(): void
    {
        $subscription = $this->subscribe($this->starter);
        $addon = $this->anAddon('50 more products', Addon::KIND_UNITS, 'products', 50, '300');

        $payment = app(Payments::class)->requestAddon($addon, 1, $this->store);
        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);

        // 990 for the plan, 300 for the extra.
        $this->assertSame(129000, app(Payments::class)->amountDue($subscription->fresh())->minor);
    }

    public function test_a_platform_ceiling_still_holds_over_any_extra(): void
    {
        $this->subscribe($this->growth);

        // Growth allows one web address; the platform never allows more than
        // two, whatever is bought.
        $addon = $this->anAddon('More web addresses', Addon::KIND_UNITS, 'custom_domains', 5, '500');

        $payment = app(Payments::class)->requestAddon($addon, 3, $this->store);
        app(Payments::class)->confirm($payment, $this->store, Admin::factory()->create());

        Tenancy::set($this->store);
        Entitlements::forget();

        $this->assertSame(2, Entitlements::limit('custom_domains'));
    }

    public function test_staff_can_only_sell_an_extra_that_matches_what_it_adds_to(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(AddonIndex::class)
            ->call('add')
            ->set('name', 'Nonsense')
            ->set('feature', 'discount_codes')
            ->set('kind', Addon::KIND_UNITS)
            ->call('save')
            ->assertHasErrors('kind');

        $this->assertSame(0, Addon::count());
    }

    public function test_staff_can_create_an_extra_and_price_it_per_market(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(AddonIndex::class)
            ->call('add')
            ->set('name', '50 more products')
            ->set('feature', 'products')
            ->set('kind', Addon::KIND_UNITS)
            ->set('unit_amount', '50')
            ->set('prices.BDT', '300')
            ->set('prices.MYR', '12')
            ->call('save')
            ->assertHasNoErrors();

        $addon = Addon::firstOrFail();

        $this->assertSame(30000, $addon->priceIn('BDT')->minor);
        $this->assertSame(1200, $addon->priceIn('MYR')->minor);
        $this->assertNull($addon->priceIn('USD'));
    }

    /*
     * ---------------------------------------------------------------
     * The staff screen
     * ---------------------------------------------------------------
     */

    public function test_staff_see_what_is_waiting_and_can_confirm_it(): void
    {
        $subscription = $this->subscribe($this->starter);

        app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash', '8N7A2K9QX1', null, [], $subscription,
        );

        Tenancy::forget();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(BillingIndex::class)
            ->assertSee($this->store->name)
            ->assertSee('8N7A2K9QX1')
            ->assertSee('990.00')
            ->call('confirm', SubscriptionPayment::query()
                ->withoutGlobalScope(TenantScope::class)->firstOrFail()->id);

        Tenancy::set($this->store);

        $this->assertSame(Subscription::STATUS_ACTIVE, Subscription::query()->active()->first()->status);
    }

    public function test_staff_must_say_why_when_they_cannot_find_a_payment(): void
    {
        $subscription = $this->subscribe($this->starter);

        app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL,
            'bkash', 'NOPE', null, [], $subscription,
        );

        $id = SubscriptionPayment::query()->firstOrFail()->id;

        Tenancy::forget();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(BillingIndex::class)
            ->call('reject', $id)
            ->assertSet('messageType', 'error');

        Tenancy::set($this->store);
        $this->assertTrue(SubscriptionPayment::find($id)->isWaiting());
    }

    /*
     * ---------------------------------------------------------------
     * One shop's billing is its own
     * ---------------------------------------------------------------
     */

    public function test_one_shop_never_sees_another_shops_payments_or_extras(): void
    {
        $subscription = $this->subscribe($this->starter);
        app(Payments::class)->claim(
            Money::fromDecimal('990', 'BDT', 2),
            SubscriptionPayment::PURPOSE_RENEWAL, 'bkash', 'MINE', null, [], $subscription,
        );

        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);

        Tenancy::run($other, function () {
            $this->assertSame(0, SubscriptionPayment::count());
            $this->assertSame(0, SubscriptionAddon::count());
        });
    }
}
