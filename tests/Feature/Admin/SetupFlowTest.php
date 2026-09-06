<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\Dashboard;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SetupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->user = User::factory()->create(['tenant_id' => $this->store->id]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    public function test_the_steps_show_by_default_with_the_next_one_pointed_out(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('setupHidden', false)
            ->assertSee('Getting started')
            ->assertSee('Add the first thing you sell.')
            ->assertSee('steps to go');
    }

    public function test_an_unfinished_step_is_a_link_to_where_it_gets_done(): void
    {
        $html = Livewire::test(Dashboard::class)->html();

        // The step itself is a link, carrying the words for what to do.
        $this->assertMatchesRegularExpression(
            '~<a href="'.preg_quote(route('admin.products.create'), '~').'"[^>]*>.*?Add the first thing you sell~s',
            $html,
        );
    }

    public function test_the_next_step_moves_along_as_work_is_done(): void
    {
        Livewire::test(Dashboard::class)->assertSee('Add the first thing you sell.');

        app(ProductService::class)->create(['name' => 'First', 'regular_price' => '100']);

        // With something to sell, the nudge becomes: put it on sale.
        Livewire::test(Dashboard::class)->assertSee('Publish a product so people can buy it.');
    }

    public function test_the_steps_can_be_put_away_and_stay_away(): void
    {
        Livewire::test(Dashboard::class)
            ->call('hideSetup')
            ->assertSet('setupHidden', true)
            ->assertDispatched('toast')
            ->assertSee('Setting up your shop')
            ->assertDontSee('Getting started');

        $this->assertTrue($this->user->fresh()->prefers('setup_hidden'));

        // Coming back later, it is still put away.
        Livewire::test(Dashboard::class)->assertSet('setupHidden', true);
    }

    public function test_the_steps_can_be_brought_back(): void
    {
        Livewire::test(Dashboard::class)->call('hideSetup');

        Livewire::test(Dashboard::class)
            ->call('showSetup')
            ->assertSet('setupHidden', false)
            ->assertSee('Getting started');

        $this->assertFalse($this->user->fresh()->prefers('setup_hidden'));
    }

    public function test_putting_them_away_is_that_persons_own_choice(): void
    {
        Livewire::test(Dashboard::class)->call('hideSetup');

        $colleague = User::factory()->create(['tenant_id' => $this->store->id]);

        $this->actingAs($colleague);

        Livewire::test(Dashboard::class)->assertSet('setupHidden', false);
        $this->assertNull($colleague->fresh()->prefers('setup_hidden'));
    }

    public function test_the_put_away_line_still_shows_how_far_along_the_shop_is(): void
    {
        app(ProductService::class)->create([
            'name' => 'Live', 'regular_price' => '100', 'status' => Product::STATUS_ACTIVE,
        ]);

        Livewire::test(Dashboard::class)
            ->call('hideSetup')
            ->assertSee('Setting up your shop')
            ->assertSee('of 7 done');
    }

    public function test_a_shop_with_everything_done_is_congratulated_rather_than_nagged(): void
    {
        // Everything on the list, including a verified domain of their own.
        $product = app(ProductService::class)->create([
            'name' => 'Panjabi',
            'regular_price' => '1450',
            'cost_price' => '900',
            'status' => Product::STATUS_ACTIVE,
        ]);

        Category::create(['name' => 'Menswear', 'slug' => 'menswear']);

        ProductImage::create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'shops/1/photo.jpg',
            'is_primary' => true,
        ]);

        Domain::factory()->custom('shop.example.com')->verified()->create(['tenant_id' => $this->store->id]);

        Livewire::test(Dashboard::class)
            ->assertSee('Your shop is ready')
            ->assertSee('100%')
            ->assertDontSee('steps to go');
    }
}
