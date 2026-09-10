<?php

namespace Tests\Feature\Super;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Super\StoreForm;
use App\Models\Admin;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opening a shop from behind the counter.
 *
 * Until now this could only be done over SSH. The screen does exactly what
 * the command did — shop, free address, plan, owner's sign-in — and writes
 * it down.
 */
class AddShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Admin::factory()->create(['name' => 'Md Jewel Rana']), 'admin');

        Package::factory()->create([
            'name' => 'Starter', 'slug' => 'starter', 'currency' => 'BDT',
            'price_minor' => 99000, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_a_shop_its_address_its_plan_and_its_owner_are_made_together(): void
    {
        Livewire::test(StoreForm::class)
            ->set('name', 'Dhaka Fashion')
            ->set('country', 'BD')
            ->set('plan', 'starter')
            ->set('ownerName', 'Rahim Uddin')
            ->set('ownerEmail', 'rahim@example.com')
            ->set('ownerPassword', 'a-long-enough-one')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Dhaka Fashion');

        $shop = Tenant::where('slug', 'dhaka-fashion')->firstOrFail();

        $this->assertSame(Tenant::STATUS_ACTIVE, $shop->status);
        $this->assertSame('BD', $shop->country_code);
        $this->assertSame('BDT', $shop->currency);

        Tenancy::run($shop, function () {
            $this->assertTrue(Domain::where('is_primary', true)->exists());

            $owner = User::where('email', 'rahim@example.com')->firstOrFail();
            $this->assertSame(User::ROLE_OWNER, $owner->role);
            $this->assertTrue(Hash::check('a-long-enough-one', $owner->password));

            $this->assertSame('starter', Subscription::query()->active()->firstOrFail()->package->slug);
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shop.created',
            'tenant_id' => $shop->id,
            'admin_name' => 'Md Jewel Rana',
        ]);
    }

    public function test_choosing_a_country_prices_the_shop_in_that_market(): void
    {
        Livewire::test(StoreForm::class)
            ->set('country', 'MY')
            ->assertSet('currency', 'MYR')
            ->set('country', 'ID')
            ->assertSet('currency', 'IDR');
    }

    public function test_a_shop_is_not_opened_without_an_owner_who_can_sign_in(): void
    {
        Livewire::test(StoreForm::class)
            ->set('name', 'Half A Shop')
            ->set('plan', 'starter')
            ->call('save')
            ->assertHasErrors(['ownerName', 'ownerEmail', 'ownerPassword']);

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_two_shops_cannot_share_a_web_address(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);

        Livewire::test(StoreForm::class)
            ->set('name', 'Another One')
            ->set('slug', 'taken')
            ->set('plan', 'starter')
            ->set('ownerName', 'Somebody')
            ->set('ownerEmail', 'somebody@example.com')
            ->set('ownerPassword', 'a-long-enough-one')
            ->call('save')
            ->assertHasErrors('slug');
    }

    public function test_a_short_password_is_refused(): void
    {
        Livewire::test(StoreForm::class)
            ->set('name', 'Dhaka Fashion')
            ->set('plan', 'starter')
            ->set('ownerName', 'Rahim Uddin')
            ->set('ownerEmail', 'rahim@example.com')
            ->set('ownerPassword', 'short')
            ->call('save')
            ->assertHasErrors('ownerPassword');

        $this->assertDatabaseCount('tenants', 0);
    }
}
