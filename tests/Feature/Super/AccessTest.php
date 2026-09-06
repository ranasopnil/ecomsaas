<?php

namespace Tests\Feature\Super;

use App\Facades\Tenancy;
use App\Models\Admin;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    public function test_a_stranger_is_sent_to_the_sign_in_page(): void
    {
        $this->get('/super/plans')->assertRedirect('/super/login');
    }

    public function test_staff_can_sign_in(): void
    {
        $admin = Admin::factory()->create();

        $this->post('/super/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/super');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $admin = Admin::factory()->create();

        $this->post('/super/login', [
            'email' => $admin->email,
            'password' => 'guessing',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_a_switched_off_staff_account_cannot_sign_in(): void
    {
        $admin = Admin::factory()->inactive()->create();

        $this->post('/super/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_a_merchant_account_cannot_sign_in_as_staff(): void
    {
        $user = User::factory()->create();

        $this->post('/super/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_a_shop_address_does_not_even_hint_that_staff_pages_exist(): void
    {
        $store = Tenant::factory()->create();
        $domain = Tenancy::run($store, fn () => Domain::factory()->create(['tenant_id' => $store->id]));

        // Signed out: it must be a plain "not found", never a redirect to the
        // staff sign-in page, which would tell a merchant the page is there.
        $this->get('https://'.$domain->hostname.'/super/plans')->assertNotFound();
        $this->get('https://'.$domain->hostname.'/super/login')->assertNotFound();
    }

    public function test_the_super_admin_does_not_exist_on_a_shop_address(): void
    {
        $store = Tenant::factory()->create();
        $domain = Tenancy::run($store, fn () => Domain::factory()->create(['tenant_id' => $store->id]));

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('https://'.$domain->hostname.'/super/plans')
            ->assertNotFound();
    }
}
