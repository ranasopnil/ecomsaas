<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());
        Entitlements::forget();

        $this->domain = Tenancy::run($this->store, fn () => Domain::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function make(array $attributes = []): Product
    {
        return Tenancy::run($this->store, fn () => app(ProductService::class)->create(array_merge([
            'name' => 'Cotton Panjabi',
            'price' => '1450.50',
            'status' => Product::STATUS_ACTIVE,
            'stock' => 5,
        ], $attributes)));
    }

    protected function url(Product $product): string
    {
        return 'https://'.$this->domain->hostname.'/products/'.$product->slug;
    }

    public function test_a_shopper_sees_a_product_that_is_on_sale(): void
    {
        $product = $this->make();

        Tenancy::forget();

        $this->get($this->url($product))
            ->assertOk()
            ->assertSee('Cotton Panjabi')
            ->assertSee('1450.50')
            ->assertSee('In stock');
    }

    public function test_a_shopper_cannot_see_a_product_that_is_not_on_sale_yet(): void
    {
        $product = $this->make(['status' => Product::STATUS_DRAFT]);

        Tenancy::forget();

        $this->get($this->url($product))->assertNotFound();
    }

    public function test_the_shopkeeper_can_preview_a_product_before_it_goes_on_sale(): void
    {
        $product = $this->make(['status' => Product::STATUS_DRAFT]);
        $user = Tenancy::run($this->store, fn () => User::factory()->create(['tenant_id' => $this->store->id]));

        Tenancy::forget();

        $this->actingAs($user)
            ->get($this->url($product))
            ->assertOk()
            ->assertSee('It is not on sale yet, so nobody else can see it');
    }

    public function test_a_sold_out_product_says_so(): void
    {
        $product = $this->make(['stock' => 0]);

        Tenancy::forget();

        $this->get($this->url($product))->assertOk()->assertSee('Sold out');
    }

    public function test_one_shop_cannot_open_another_shops_product(): void
    {
        $product = $this->make();

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());
        $otherDomain = Tenancy::run($other, fn () => Domain::factory()->create(['tenant_id' => $other->id]));

        Tenancy::forget();

        $this->get('https://'.$otherDomain->hostname.'/products/'.$product->slug)->assertNotFound();
    }

    public function test_the_description_is_shown_as_written_but_scripts_never_survive(): void
    {
        $product = $this->make([
            'description' => '<h2>About this</h2><p>Soft cotton.</p><script>alert(1)</script>',
        ]);

        Tenancy::forget();

        $response = $this->get($this->url($product))->assertOk();

        $response->assertSee('About this', false);
        $response->assertSee('<p>Soft cotton.</p>', false);
        $response->assertDontSee('alert(1)', false);
    }

    public function test_the_choices_a_shopper_can_make_are_listed(): void
    {
        $product = $this->make();

        Tenancy::run($this->store, fn () => app(ProductService::class)
            ->setOptions($product, [['name' => 'Size', 'values' => ['Small', 'Large']]]));

        Tenancy::forget();

        $this->get($this->url($product))
            ->assertOk()
            ->assertSee('Size')
            ->assertSee('Small')
            ->assertSee('Large');
    }
}
