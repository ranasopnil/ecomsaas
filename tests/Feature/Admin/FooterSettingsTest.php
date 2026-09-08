<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\FooterSettings;
use App\Models\Domain;
use App\Models\Package;
use App\Models\StorefrontFooter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shopkeeper filling in the bottom of their own shop.
 *
 * One screen holds the address, where else they can be found, and the pages a
 * customer reads before buying. Nothing typed here is ever run: links are
 * tidied into ordinary web addresses or refused.
 */
class FooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'email' => 'owner@dhakashop.test']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_the_screen_opens_from_the_shop_dashboard(): void
    {
        $hostname = Tenancy::run($this->store, fn () => Domain::factory()->create([
            'tenant_id' => $this->store->id,
        ])->hostname);

        $this->get('https://'.$hostname.'/admin/footer')
            ->assertOk()
            ->assertSee('Footer &amp; pages', false)
            ->assertSee('Where else people find you')
            ->assertSee('Your pages');
    }

    public function test_the_screen_starts_from_the_address_the_shop_signed_up_with(): void
    {
        Livewire::test(FooterSettings::class)
            ->assertSet('contact_email', 'owner@dhakashop.test')
            ->assertSee('Footer')
            ->assertSee('Refund policy');
    }

    public function test_a_shopkeeper_can_fill_in_the_footer_and_it_is_stored(): void
    {
        Livewire::test(FooterSettings::class)
            ->set('about', 'A family shop in Dhanmondi.')
            ->set('address', 'House 12, Road 5, Dhanmondi')
            ->set('phone', '+880 1712 345678')
            ->set('whatsapp_number', '+880 1712 345678')
            ->set('refund_policy', 'Seven days to bring it back.')
            ->call('save')
            ->assertHasNoErrors();

        $footer = StorefrontFooter::query()->first();

        $this->assertSame('A family shop in Dhanmondi.', $footer->about);
        $this->assertSame('Seven days to bring it back.', $footer->refund_policy);
        $this->assertSame('https://wa.me/8801712345678', $footer->whatsappUrl());
        $this->assertSame($this->store->id, $footer->tenant_id);
    }

    public function test_a_link_typed_without_the_https_is_stored_as_one_that_opens(): void
    {
        Livewire::test(FooterSettings::class)
            ->set('facebook_url', 'facebook.com/dhakashop')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('facebook_url', 'https://facebook.com/dhakashop');

        $this->assertSame('https://facebook.com/dhakashop', StorefrontFooter::query()->first()->facebook_url);
    }

    public function test_something_that_is_not_a_web_address_is_refused(): void
    {
        Livewire::test(FooterSettings::class)
            ->set('instagram_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('instagram_url');

        $this->assertNull(StorefrontFooter::query()->first());
    }

    public function test_a_bad_contact_email_or_phone_number_is_refused(): void
    {
        Livewire::test(FooterSettings::class)
            ->set('contact_email', 'not an email')
            ->set('whatsapp_number', 'call me')
            ->call('save')
            ->assertHasErrors(['contact_email', 'whatsapp_number']);
    }

    public function test_saving_twice_changes_the_one_footer_rather_than_making_another(): void
    {
        Livewire::test(FooterSettings::class)
            ->set('about', 'First words.')
            ->call('save')
            ->set('about', 'Second words.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, StorefrontFooter::query()->count());
        $this->assertSame('Second words.', StorefrontFooter::query()->first()->about);
    }

    public function test_emptying_a_page_takes_it_off_the_shop(): void
    {
        StorefrontFooter::create(['tenant_id' => $this->store->id, 'terms' => 'The old rules.']);

        Livewire::test(FooterSettings::class)
            ->assertSet('terms', 'The old rules.')
            ->set('terms', '')
            ->call('save')
            ->assertHasNoErrors();

        $footer = StorefrontFooter::query()->first();

        $this->assertNull($footer->terms);
        $this->assertTrue($footer->writtenPages()->isEmpty());
    }
}
