<?php

namespace Tests\Feature\Billing;

use App\Exceptions\ImmutableFinancialRecord;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_subscribing_records_the_price_agreed_today(): void
    {
        $store = Tenant::factory()->create();
        $package = Package::factory()->create(['price_minor' => 249000, 'currency' => 'BDT']);

        $subscription = app(SubscribeToPackage::class)->handle($store, $package);

        $this->assertSame(249000, $subscription->price_minor);
        $this->assertSame('BDT 2490.00', (string) $subscription->price);

        $package->update(['price_minor' => 499000]);

        $this->assertSame(249000, $subscription->fresh()->price_minor);
    }

    public function test_a_trial_is_only_given_on_a_stores_first_subscription(): void
    {
        $store = Tenant::factory()->create();
        $package = Package::factory()->withTrial(14)->create();

        $first = app(SubscribeToPackage::class)->handle($store, $package);
        $this->assertSame(Subscription::STATUS_TRIALING, $first->status);
        $this->assertNotNull($first->trial_ends_at);

        $second = app(SubscribeToPackage::class)->handle($store, $package);
        $this->assertSame(Subscription::STATUS_ACTIVE, $second->status);
        $this->assertNull($second->trial_ends_at);
    }

    public function test_changing_plan_ends_the_old_subscription_instead_of_editing_it(): void
    {
        $store = Tenant::factory()->create();
        $starter = Package::factory()->create(['price_minor' => 99000]);
        $growth = Package::factory()->create(['price_minor' => 249000]);

        $first = app(SubscribeToPackage::class)->handle($store, $starter);
        $second = app(SubscribeToPackage::class)->handle($store, $growth);

        Tenancy::run($store, function () use ($first, $second) {
            $this->assertSame(2, Subscription::count());
            $this->assertSame(Subscription::STATUS_CANCELLED, $first->fresh()->status);
            $this->assertNotNull($first->fresh()->ends_at);
            $this->assertTrue($second->fresh()->isActive());
            $this->assertSame(1, Subscription::query()->active()->count());
        });
    }

    public function test_the_agreed_price_cannot_be_edited(): void
    {
        $store = Tenant::factory()->create();
        $subscription = app(SubscribeToPackage::class)->handle($store, Package::factory()->create());

        $this->expectException(ImmutableFinancialRecord::class);

        Tenancy::run($store, fn () => $subscription->update(['price_minor' => 1]));
    }

    public function test_a_plan_change_rewrites_what_the_store_may_do(): void
    {
        $store = Tenant::factory()->create();
        $starter = Package::factory()->allowing(['products' => 50, 'online_payments' => false])->create();
        $growth = Package::factory()->allowing(['products' => 1000, 'online_payments' => true])->create();

        app(SubscribeToPackage::class)->handle($store, $starter);

        Tenancy::run($store, function () {
            $this->assertSame(50, Entitlements::limit('products'));
            $this->assertFalse(Entitlements::allows('online_payments'));
        });

        app(SubscribeToPackage::class)->handle($store, $growth);

        Tenancy::run($store, function () {
            $this->assertSame(1000, Entitlements::limit('products'));
            $this->assertTrue(Entitlements::allows('online_payments'));
        });
    }

    public function test_a_super_admin_override_survives_a_plan_change(): void
    {
        $store = Tenant::factory()->create();
        $starter = Package::factory()->allowing(['products' => 50])->create();
        $growth = Package::factory()->allowing(['products' => 1000])->create();

        app(SubscribeToPackage::class)->handle($store, $starter);

        Tenancy::run($store, fn () => TenantEntitlement::query()->updateOrCreate(
            ['tenant_id' => $store->id, 'feature' => 'products'],
            ['enabled' => true, 'limit_value' => 5000, 'source' => TenantEntitlement::SOURCE_OVERRIDE, 'note' => 'Agreed by phone'],
        ));

        Entitlements::forget($store->id);

        app(SubscribeToPackage::class)->handle($store, $growth);

        Tenancy::run($store, fn () => $this->assertSame(5000, Entitlements::limit('products')));
    }

    public function test_one_stores_plan_is_invisible_to_another(): void
    {
        [$storeA, $storeB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $package = Package::factory()->allowing(['products' => 50])->create();

        app(SubscribeToPackage::class)->handle($storeA, $package);

        Tenancy::run($storeB, function () {
            $this->assertSame(0, Subscription::count());
            $this->assertSame(0, TenantEntitlement::count());
        });
    }
}
