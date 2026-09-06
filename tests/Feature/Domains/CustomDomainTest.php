<?php

namespace Tests\Feature\Domains;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\DomainIndex;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Domains\DnsResolver;
use App\Services\Domains\DomainChecker;
use App\Services\Domains\DomainRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.server_ips' => ['46.224.138.153']]);

        $this->store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle(
            $this->store,
            Package::factory()->allowing(['custom_domains' => 1, 'products' => 50])->create()
        );

        Entitlements::forget();
        Tenancy::set($this->store);

        Tenancy::run($this->store, fn () => Domain::factory()->create(['tenant_id' => $this->store->id]));

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    /**
     * Answer for the name server, so no test goes near the real internet.
     *
     * @param  array<int, string>  $addresses
     */
    protected function dnsAnswers(array $addresses): void
    {
        $this->swap(DnsResolver::class, new class($addresses) extends DnsResolver
        {
            /** @param array<int, string> $addresses */
            public function __construct(protected array $addresses) {}

            public function addressesFor(string $hostname): array
            {
                return $this->addresses;
            }
        });
    }

    public function test_a_merchant_can_add_their_own_domain(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        $this->assertSame('myshop.com', $domain->hostname);
        $this->assertSame(Domain::STATUS_PENDING, $domain->status);

        // The www version is added alongside, because customers type both.
        Tenancy::run($this->store, fn () => $this->assertNotNull(Domain::findByHostname('www.myshop.com')));
    }

    public function test_a_pasted_link_is_tidied_into_an_address(): void
    {
        $registrar = app(DomainRegistrar::class);

        $this->assertSame('myshop.com', $registrar->tidy('https://myshop.com/products?a=1'));
        $this->assertSame('myshop.com', $registrar->tidy('  HTTP://MyShop.com/  '));
        $this->assertSame('shop.myshop.com', $registrar->tidy('shop.myshop.com:443'));
    }

    public function test_nonsense_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DomainRegistrar::class)->add($this->store, 'not a domain');
    }

    public function test_a_platform_address_cannot_be_claimed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DomainRegistrar::class)->add($this->store, 'somebodyelse'.config('tenancy.subdomain_suffix'));
    }

    public function test_an_address_another_shop_already_uses_is_refused(): void
    {
        app(DomainRegistrar::class)->add($this->store, 'taken.com');

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['custom_domains' => 2])->create());
        Entitlements::forget();

        $this->expectException(InvalidArgumentException::class);

        app(DomainRegistrar::class)->add($other, 'taken.com');
    }

    public function test_the_plan_decides_how_many_domains_a_shop_may_have(): void
    {
        app(DomainRegistrar::class)->add($this->store, 'first.com');

        $this->expectException(LimitReached::class);

        app(DomainRegistrar::class)->add($this->store, 'second.com');
    }

    public function test_a_domain_pointing_here_is_verified_without_any_code_to_paste(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        $this->dnsAnswers(['46.224.138.153']);

        $this->assertTrue(app(DomainChecker::class)->check($domain));

        $domain->refresh();
        $this->assertSame(Domain::STATUS_VERIFIED, $domain->status);
        $this->assertNotNull($domain->verified_at);
    }

    public function test_a_domain_pointing_somewhere_else_is_told_where_it_points(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        $this->dnsAnswers(['203.0.113.9']);

        $this->assertFalse(app(DomainChecker::class)->check($domain));

        $domain->refresh();
        $this->assertSame(Domain::STATUS_PENDING, $domain->status);
        $this->assertStringContainsString('203.0.113.9', $domain->last_check_result);
        $this->assertStringContainsString('46.224.138.153', $domain->last_check_result);
    }

    public function test_a_domain_behind_cloudflare_is_told_to_use_the_grey_cloud(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        $this->dnsAnswers(['104.21.5.7']);

        app(DomainChecker::class)->check($domain);

        $this->assertStringContainsString('DNS only', $domain->fresh()->last_check_result);
    }

    public function test_a_domain_with_no_record_yet_says_to_wait(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        $this->dnsAnswers([]);

        app(DomainChecker::class)->check($domain);

        $this->assertStringContainsString('No A record found yet', $domain->fresh()->last_check_result);
    }

    public function test_the_shop_answers_on_the_domain_once_it_is_verified(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        Tenancy::forget();

        // Not yet: an unverified domain is nobody's shop.
        $this->get('https://myshop.com/')->assertNotFound();

        $this->dnsAnswers(['46.224.138.153']);
        Tenancy::run($this->store, fn () => app(DomainChecker::class)->check($domain));
        Tenancy::forget();

        $this->get('https://myshop.com/')->assertOk()->assertSee($this->store->name);
    }

    public function test_caddy_is_only_told_yes_for_a_verified_domain(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        Tenancy::forget();

        $this->get('http://127.0.0.1/internal/domain-check?domain=myshop.com')->assertNotFound();

        $this->dnsAnswers(['46.224.138.153']);
        Tenancy::run($this->store, fn () => app(DomainChecker::class)->check($domain));
        Tenancy::forget();

        $this->get('http://127.0.0.1/internal/domain-check?domain=myshop.com')->assertOk();
    }

    public function test_the_main_address_can_be_changed_but_only_to_a_working_one(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        Livewire::test(DomainIndex::class)
            ->call('makePrimary', $domain->id)
            ->assertDispatched('toast');

        $this->assertFalse($domain->fresh()->is_primary, 'A domain that is not working yet cannot be the main one.');

        $this->dnsAnswers(['46.224.138.153']);
        app(DomainChecker::class)->check($domain);

        Livewire::test(DomainIndex::class)->call('makePrimary', $domain->id);

        $this->assertTrue($domain->fresh()->is_primary);
    }

    public function test_removing_a_domain_takes_its_www_with_it_and_leaves_a_main_address(): void
    {
        $domain = app(DomainRegistrar::class)->add($this->store, 'myshop.com');

        Livewire::test(DomainIndex::class)->call('remove', $domain->id);

        Tenancy::run($this->store, function () {
            $this->assertNull(Domain::findByHostname('myshop.com'));
            $this->assertNull(Domain::findByHostname('www.myshop.com'));
            $this->assertTrue(Domain::where('is_primary', true)->exists(), 'The shop must always have a main address.');
        });
    }

    public function test_the_free_shop_address_cannot_be_removed(): void
    {
        $free = Tenancy::run($this->store, fn () => Domain::where('type', Domain::TYPE_SUBDOMAIN)->firstOrFail());

        Livewire::test(DomainIndex::class)
            ->call('remove', $free->id)
            ->assertDispatched('toast');

        $this->assertNotNull($free->fresh());
    }

    public function test_the_screen_shows_the_record_to_create(): void
    {
        Livewire::test(DomainIndex::class)
            ->assertSee('46.224.138.153')
            ->assertSee('Pointing your domain here')
            ->assertSee('What not to do')
            ->assertSee('DNS only');
    }

    public function test_a_shop_whose_plan_excludes_domains_is_told_to_upgrade(): void
    {
        $basic = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($basic, Package::factory()->allowing(['custom_domains' => 0])->create());
        Entitlements::forget();

        Tenancy::run($basic, function () use ($basic) {
            $this->actingAs(User::factory()->create(['tenant_id' => $basic->id]));

            Livewire::test(DomainIndex::class)
                ->assertSee('Not included in your plan')
                ->set('hostname', 'myshop.com')
                ->call('add')
                ->assertHasErrors('hostname');
        });
    }
}
