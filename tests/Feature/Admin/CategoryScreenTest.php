<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\CategoryIndex;
use App\Livewire\Admin\ProductForm;
use App\Models\Category;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryScreenTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT']);
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

    /**
     * @return array<string, Category>
     */
    protected function nest(): array
    {
        $home = Category::create(['name' => 'Home', 'slug' => 'home']);
        $bedding = Category::create(['name' => 'Bedding', 'slug' => 'bedding', 'parent_id' => $home->id]);
        $quilts = Category::create(['name' => 'Quilts', 'slug' => 'quilts', 'parent_id' => $bedding->id]);

        return compact('home', 'bedding', 'quilts');
    }

    public function test_a_category_inside_another_shows_where_it_sits(): void
    {
        ['quilts' => $quilts] = $this->nest();

        $this->assertSame('Home › Bedding › Quilts', $quilts->path());
    }

    public function test_the_path_works_even_when_the_chain_was_not_fetched(): void
    {
        ['quilts' => $quilts] = $this->nest();

        // Fetched on its own, with nothing loaded alongside it. This is what
        // broke the categories page: walking up threw instead of loading.
        $fresh = Category::findOrFail($quilts->id);

        $this->assertSame('Home › Bedding › Quilts', $fresh->path());
    }

    public function test_the_categories_screen_opens_with_nested_categories(): void
    {
        $this->nest();

        Livewire::test(CategoryIndex::class)
            ->assertOk()
            ->assertSee('Home › Bedding')
            ->assertSee('Home › Bedding › Quilts');
    }

    public function test_the_product_form_lists_nested_categories(): void
    {
        $this->nest();

        Livewire::test(ProductForm::class)
            ->assertOk()
            ->assertSee('Home › Bedding › Quilts');
    }

    public function test_a_category_cannot_be_put_inside_itself(): void
    {
        ['home' => $home] = $this->nest();

        Livewire::test(CategoryIndex::class)
            ->call('edit', $home->id)
            ->set('parent_id', $home->id)
            ->call('save')
            ->assertHasErrors('parent_id');
    }

    public function test_a_category_holding_products_is_not_deleted_by_accident(): void
    {
        ['bedding' => $bedding] = $this->nest();

        $product = app(ProductService::class)->create([
            'name' => 'Quilt', 'regular_price' => '2000', 'category_ids' => [$bedding->id],
        ]);

        Livewire::test(CategoryIndex::class)
            ->call('delete', $bedding->id)
            ->assertDispatched('toast');

        $this->assertNotNull($bedding->fresh());
        $this->assertSame(1, $product->categories()->count());
    }

    public function test_a_broken_chain_cannot_loop_for_ever(): void
    {
        ['home' => $home, 'bedding' => $bedding] = $this->nest();

        // A loop should never happen, but if data ever did get into this state
        // the page must still open rather than hang.
        $home->forceFill(['parent_id' => $bedding->id])->saveQuietly();

        $this->assertNotEmpty(Category::findOrFail($bedding->id)->path());
    }
}
