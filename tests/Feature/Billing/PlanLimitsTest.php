<?php

namespace Tests\Feature\Billing;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function storeOn(array $entitlements): Tenant
    {
        $store = Tenant::factory()->create();
        app(SubscribeToPackage::class)->handle($store, Package::factory()->allowing($entitlements)->create());
        Entitlements::forget($store->id);

        return $store;
    }

    public function test_adding_within_the_plan_is_allowed(): void
    {
        $store = $this->storeOn(['products' => 50]);

        Tenancy::run($store, function () {
            Entitlements::ensureCanAdd('products', 49);

            $this->assertSame(1, Entitlements::remaining('products', 49));
        });
    }

    public function test_going_past_the_plan_is_refused_with_a_plain_message(): void
    {
        $store = $this->storeOn(['products' => 50]);

        Tenancy::run($store, function () {
            try {
                Entitlements::ensureCanAdd('products', 50);
                $this->fail('The 51st product should have been refused.');
            } catch (LimitReached $e) {
                $this->assertSame('products', $e->feature);
                $this->assertSame('Your plan allows 50 products. Upgrade your plan to add more.', $e->getMessage());
            }
        });
    }

    public function test_adding_several_at_once_cannot_jump_the_ceiling(): void
    {
        $store = $this->storeOn(['products' => 50]);

        $this->expectException(LimitReached::class);

        Tenancy::run($store, fn () => Entitlements::ensureCanAdd('products', 48, 5));
    }

    public function test_a_plan_with_no_ceiling_never_refuses(): void
    {
        $store = $this->storeOn(['products' => null]);

        Tenancy::run($store, function () {
            Entitlements::ensureCanAdd('products', 1_000_000);

            $this->assertNull(Entitlements::limit('products'));
            $this->assertNull(Entitlements::remaining('products', 1_000_000));
        });
    }

    public function test_a_feature_not_in_the_plan_is_refused(): void
    {
        $store = $this->storeOn(['online_payments' => false]);

        Tenancy::run($store, function () {
            $this->assertFalse(Entitlements::allows('online_payments'));

            try {
                Entitlements::ensureAllows('online_payments');
                $this->fail('Online payments should have been refused.');
            } catch (LimitReached $e) {
                $this->assertSame(
                    'take payment online is not included in your plan. Upgrade your plan to use it.',
                    $e->getMessage()
                );
            }
        });
    }

    public function test_a_store_with_no_live_plan_can_do_nothing(): void
    {
        $store = Tenant::factory()->create();

        Tenancy::run($store, function () {
            $this->assertSame(0, Entitlements::limit('products'));
            $this->assertFalse(Entitlements::allows('cash_on_delivery'));
        });
    }

    public function test_asking_about_an_unknown_feature_is_a_mistake_not_a_silent_yes(): void
    {
        $store = $this->storeOn(['products' => 50]);

        $this->expectException(InvalidArgumentException::class);

        Tenancy::run($store, fn () => Entitlements::allows('teleportation'));
    }

    public function test_the_plan_summary_lists_every_feature(): void
    {
        $store = $this->storeOn(['products' => 50, 'online_payments' => true]);

        Tenancy::run($store, function () {
            $summary = Entitlements::all();

            $this->assertSame(array_keys(config('features')), array_keys($summary));
            $this->assertSame(50, $summary['products']['limit']);
            $this->assertTrue($summary['online_payments']['enabled']);
        });
    }
}
