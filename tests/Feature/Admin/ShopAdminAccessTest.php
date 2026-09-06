<?php

namespace Tests\Feature\Admin;

use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    /**
     * @return array{0: Tenant, 1: Domain, 2: User}
     */
    protected function shopWithOwner(string $email = 'owner@example.com'): array
    {
        $store = Tenant::factory()->create();

        [$domain, $user] = Tenancy::run($store, fn () => [
            Domain::factory()->create(['tenant_id' => $store->id]),
            User::factory()->create(['tenant_id' => $store->id, 'email' => $email, 'role' => User::ROLE_OWNER]),
        ]);

        return [$store, $domain, $user];
    }

    public function test_a_shopkeeper_can_sign_in_to_their_own_shop(): void
    {
        [$store, $domain, $user] = $this->shopWithOwner();

        $this->post('https://'.$domain->hostname.'/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('https://'.$domain->hostname.'/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_same_email_can_belong_to_two_different_shops(): void
    {
        [, $domainA, $userA] = $this->shopWithOwner('same@example.com');
        [, $domainB, $userB] = $this->shopWithOwner('same@example.com');

        $this->assertNotSame($userA->id, $userB->id);

        $this->post('https://'.$domainA->hostname.'/admin/login', [
            'email' => 'same@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($userA);
    }

    public function test_a_shopkeeper_cannot_sign_in_at_someone_elses_shop(): void
    {
        [, , $userA] = $this->shopWithOwner('a@example.com');
        [, $domainB] = $this->shopWithOwner('b@example.com');

        $this->post('https://'.$domainB->hostname.'/admin/login', [
            'email' => $userA->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_signed_out_shopkeeper_is_sent_to_their_own_shops_sign_in_page(): void
    {
        [, $domain] = $this->shopWithOwner();

        $this->get('https://'.$domain->hostname.'/admin/products')
            ->assertRedirect('https://'.$domain->hostname.'/admin/login');
    }

    public function test_the_shop_admin_does_not_exist_on_the_platform_address(): void
    {
        $this->shopWithOwner();

        $this->get('https://devecom.gotipay.com/admin/products')->assertNotFound();
        $this->get('https://devecom.gotipay.com/admin/login')->assertNotFound();
    }

    public function test_a_switched_off_account_cannot_sign_in(): void
    {
        $store = Tenant::factory()->create();

        [$domain, $user] = Tenancy::run($store, fn () => [
            Domain::factory()->create(['tenant_id' => $store->id]),
            User::factory()->create(['tenant_id' => $store->id, 'is_active' => false]),
        ]);

        $this->post('https://'.$domain->hostname.'/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
