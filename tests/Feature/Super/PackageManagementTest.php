<?php

namespace Tests\Feature\Super;

use App\Exceptions\PlanNotPricedInCurrency;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Super\PackageForm;
use App\Livewire\Super\PackageIndex;
use App\Models\Admin;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_a_plan_can_be_created_with_a_price_in_each_market(): void
    {
        Livewire::test(PackageForm::class)
            ->set('name', 'Growth')
            ->set('slug', 'growth')
            ->set('description', 'For a shop selling every day.')
            ->set('trial_days', 14)
            ->set('prices.BDT', '2490')
            ->set('prices.MYR', '99.50')
            ->set('prices.IDR', '399000')
            ->set('limits.products', '1000')
            ->set('switches.online_payments', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('super.packages.index'));

        $package = Package::where('slug', 'growth')->firstOrFail();

        $this->assertSame(249000, $package->priceIn('BDT')->minor);
        $this->assertSame(9950, $package->priceIn('MYR')->minor);
        $this->assertSame('99.50', $package->priceIn('MYR')->toDecimal());
        $this->assertNull($package->priceIn('SAR'));

        $this->assertSame(1000, $package->fresh('entitlements')->limitFor('products'));
        $this->assertTrue($package->fresh('entitlements')->allowsFeature('online_payments'));
    }

    public function test_staff_cannot_give_a_plan_more_domains_than_the_platform_allows(): void
    {
        Livewire::test(PackageForm::class)
            ->set('name', 'Too generous')
            ->set('slug', 'too-generous')
            ->set('prices.BDT', '9990')
            ->set('limits.custom_domains', '3')
            ->call('save')
            ->assertHasErrors('limits.custom_domains');

        $this->assertDatabaseCount('packages', 0);
    }

    public function test_a_currency_without_decimals_keeps_its_real_value(): void
    {
        Livewire::test(PackageForm::class)
            ->set('name', 'Rupiah plan')
            ->set('slug', 'rupiah-plan')
            ->set('prices.IDR', '399000')
            ->call('save')
            ->assertHasNoErrors();

        $price = Package::where('slug', 'rupiah-plan')->firstOrFail()->priceIn('IDR');

        $this->assertSame(399000, $price->minor);
        $this->assertSame('399000', $price->toDecimal());
        $this->assertSame(0, $price->exponent);
    }

    public function test_decimals_are_refused_for_a_currency_that_has_none(): void
    {
        Livewire::test(PackageForm::class)
            ->set('name', 'Rupiah plan')
            ->set('slug', 'rupiah-plan')
            ->set('prices.IDR', '399000.50')
            ->call('save')
            ->assertHasErrors('prices.IDR');

        $this->assertDatabaseCount('packages', 0);
    }

    public function test_a_plan_with_no_price_anywhere_is_refused(): void
    {
        Livewire::test(PackageForm::class)
            ->set('name', 'Free lunch')
            ->set('slug', 'free-lunch')
            ->call('save')
            ->assertHasErrors('prices');

        $this->assertDatabaseCount('packages', 0);
    }

    public function test_two_plans_cannot_share_an_address_label(): void
    {
        Package::factory()->create(['slug' => 'growth']);

        Livewire::test(PackageForm::class)
            ->set('name', 'Growth again')
            ->set('slug', 'growth')
            ->set('prices.BDT', '2490')
            ->call('save')
            ->assertHasErrors('slug');
    }

    public function test_editing_a_plan_loads_what_is_already_set(): void
    {
        $package = Package::factory()->allowing(['products' => 50, 'online_payments' => false])->create();

        Livewire::test(PackageForm::class, ['package' => $package])
            ->assertSet('name', $package->name)
            ->assertSet('prices.BDT', '990.00')
            ->assertSet('limits.products', '50')
            ->assertSet('switches.online_payments', false);
    }

    public function test_raising_an_allowance_reaches_shops_already_on_the_plan(): void
    {
        $package = Package::factory()->allowing(['products' => 50])->create();

        $store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($store, $package);

        Tenancy::run($store, fn () => $this->assertSame(50, Entitlements::limit('products')));

        Livewire::test(PackageForm::class, ['package' => $package])
            ->set('limits.products', '500')
            ->call('save')
            ->assertHasNoErrors();

        Entitlements::forget();

        Tenancy::run($store, fn () => $this->assertSame(500, Entitlements::limit('products')));
    }

    public function test_changing_a_price_does_not_touch_what_a_shop_already_agreed(): void
    {
        $package = Package::factory()->create();

        $store = Tenant::factory()->create(['currency' => 'BDT']);
        $subscription = app(SubscribeToPackage::class)->handle($store, $package);

        Livewire::test(PackageForm::class, ['package' => $package])
            ->set('prices.BDT', '1990')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(99000, $subscription->fresh()->price_minor);
        $this->assertSame(199000, $package->fresh()->priceIn('BDT')->minor);
    }

    public function test_a_plan_that_has_been_sold_cannot_be_deleted(): void
    {
        $package = Package::factory()->create();

        $store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($store, $package);

        Livewire::test(PackageIndex::class)
            ->call('delete', $package->id)
            ->assertSet('messageType', 'error')
            ->assertSee('cannot be deleted');

        $this->assertNotNull($package->fresh());
    }

    public function test_an_unsold_plan_can_be_deleted(): void
    {
        $package = Package::factory()->create();

        Livewire::test(PackageIndex::class)->call('delete', $package->id);

        $this->assertNull(Package::find($package->id));
    }

    public function test_a_plan_can_be_taken_off_sale_without_affecting_its_shops(): void
    {
        $package = Package::factory()->create();

        $store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($store, $package);

        Livewire::test(PackageIndex::class)->call('toggleActive', $package->id);

        $this->assertFalse($package->fresh()->is_active);
        Tenancy::run($store, fn () => $this->assertTrue(Subscription::query()->active()->exists()));
    }

    public function test_a_shop_cannot_be_put_on_a_plan_with_no_price_in_its_currency(): void
    {
        $package = Package::factory()->create();

        $store = Tenant::factory()->create(['currency' => 'MYR']);

        $this->expectException(PlanNotPricedInCurrency::class);

        app(SubscribeToPackage::class)->handle($store, $package);
    }

    public function test_a_shop_is_charged_in_its_own_currency(): void
    {
        $package = Package::factory()->create();
        $package->prices()->create(['currency' => 'IDR', 'currency_exponent' => 0, 'price_minor' => 149000, 'billing_period' => 'monthly']);

        $jakarta = Tenant::factory()->create(['currency' => 'IDR', 'currency_exponent' => 0]);

        $subscription = app(SubscribeToPackage::class)->handle($jakarta, $package);

        $this->assertSame('IDR', $subscription->currency);
        $this->assertSame(149000, $subscription->price_minor);
        $this->assertSame('IDR 149000', (string) $subscription->price);
    }
}
