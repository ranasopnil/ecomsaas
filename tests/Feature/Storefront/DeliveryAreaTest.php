<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\DeliveryAreaForm;
use App\Livewire\Admin\ProductForm;
use App\Models\DeliveryArea;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\DeliveryReach;
use App\Support\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Named delivery areas.
 *
 * A shop draws the places it delivers to once and names them. Every product
 * goes wherever the shop goes, unless it is marked as going anywhere, or is
 * tied to particular areas picked from that list.
 */
class DeliveryAreaTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    /** Roughly the middle of Dhaka. */
    protected GeoPoint $dhaka;

    /** A few kilometres away, still in the city. */
    protected GeoPoint $gulshan;

    /** Another city entirely, about 216 km off. */
    protected GeoPoint $chittagong;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dhaka = new GeoPoint(23.8103, 90.4125);
        $this->gulshan = new GeoPoint(23.7925, 90.4078);
        $this->chittagong = new GeoPoint(22.3569, 91.7832);

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 100])->create());

        Entitlements::forget();
        Tenancy::set($this->store);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function area(string $name, GeoPoint $centre, float $km): DeliveryArea
    {
        return DeliveryArea::create([
            'tenant_id' => $this->store->id,
            'name' => $name,
            'latitude' => $centre->latitude,
            'longitude' => $centre->longitude,
            'radius_km' => $km,
        ]);
    }

    protected function product(string $name, string $availability = Product::AVAILABLE_SHOP, array $areaIds = []): Product
    {
        $created = app(ProductService::class)->create([
            'name' => $name, 'regular_price' => '100', 'stock' => 10,
            'status' => Product::STATUS_ACTIVE,
            'availability' => $availability,
            'delivery_area_ids' => $areaIds,
        ]);

        return ($created instanceof Product ? $created : $created->product)->fresh();
    }

    /**
     * @return array<int, string>
     */
    protected function seenFrom(?GeoPoint $customer): array
    {
        return Product::query()->deliverableTo($customer)->orderBy('name')->pluck('name')->all();
    }

    /*
     * ------------------------------------------------------ what a shopper sees
     */

    public function test_a_shop_with_no_named_areas_delivers_everywhere(): void
    {
        $this->product('Rice');
        $this->product('Lentils');

        $this->assertSame(['Lentils', 'Rice'], $this->seenFrom($this->chittagong));
    }

    public function test_a_customer_outside_every_area_sees_nothing_that_follows_the_shop(): void
    {
        $this->area('Dhaka city', $this->dhaka, 10);

        $this->product('Rice');
        $this->product('Milk');

        $this->assertSame(['Milk', 'Rice'], $this->seenFrom($this->gulshan));
        $this->assertSame([], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_marked_anywhere_ignores_every_area(): void
    {
        $this->area('Dhaka city', $this->dhaka, 10);

        $this->product('Rice');
        $this->product('Tea', Product::AVAILABLE_ANYWHERE);

        $this->assertSame(['Tea'], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_can_be_tied_to_one_named_area(): void
    {
        $dhaka = $this->area('Dhaka city', $this->dhaka, 20);
        $chittagong = $this->area('Chittagong', $this->chittagong, 20);

        $this->product('Ice cream', Product::AVAILABLE_AREAS, [$dhaka->id]);
        $this->product('Dried fish', Product::AVAILABLE_AREAS, [$chittagong->id]);

        $this->assertSame(['Ice cream'], $this->seenFrom($this->gulshan));
        $this->assertSame(['Dried fish'], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_can_be_tied_to_several_areas_at_once(): void
    {
        $mirpur = $this->area('Mirpur', $this->dhaka, 8);
        $chittagong = $this->area('Chittagong', $this->chittagong, 20);

        $this->product('Bread', Product::AVAILABLE_AREAS, [$mirpur->id, $chittagong->id]);

        $this->assertSame(['Bread'], $this->seenFrom($this->gulshan));
        $this->assertSame(['Bread'], $this->seenFrom($this->chittagong));
        $this->assertSame([], $this->seenFrom(new GeoPoint(24.3745, 88.6042)));   // Rajshahi
    }

    public function test_a_product_tied_to_areas_but_given_none_follows_the_shop(): void
    {
        $this->area('Dhaka city', $this->dhaka, 10);

        $this->product('Bread', Product::AVAILABLE_AREAS);

        $this->assertSame(['Bread'], $this->seenFrom($this->gulshan));
        $this->assertSame([], $this->seenFrom($this->chittagong));
    }

    public function test_a_customer_who_has_not_said_where_they_are_sees_the_whole_shop(): void
    {
        $dhaka = $this->area('Dhaka city', $this->dhaka, 1);

        $this->product('Rice');
        $this->product('Ice cream', Product::AVAILABLE_AREAS, [$dhaka->id]);

        $this->assertSame(['Ice cream', 'Rice'], $this->seenFrom(null));
    }

    public function test_the_shopkeeper_is_told_in_plain_words_where_each_product_goes(): void
    {
        $mirpur = $this->area('Mirpur', $this->dhaka, 8);
        $uttara = $this->area('Uttara', $this->dhaka, 12);
        $reach = app(DeliveryReach::class);

        $this->assertSame(
            'Wherever the shop delivers — Mirpur and Uttara',
            $reach->describe($this->product('Rice')),
        );

        $this->assertSame(
            'Delivered anywhere',
            $reach->describe($this->product('Tea', Product::AVAILABLE_ANYWHERE)),
        );

        $this->assertSame(
            'Only Mirpur and Uttara',
            $reach->describe($this->product('Milk', Product::AVAILABLE_AREAS, [$mirpur->id, $uttara->id])),
        );

        $this->assertSame(
            'Wherever the shop delivers — no areas picked yet',
            $reach->describe($this->product('Bread', Product::AVAILABLE_AREAS)),
        );
    }

    /*
     * ------------------------------------------------- naming areas on screen
     */

    protected function asShopkeeper(): void
    {
        Tenancy::set($this->store);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    public function test_a_shopkeeper_can_name_an_area_and_draw_it(): void
    {
        $this->asShopkeeper();

        Livewire::test(DeliveryAreaForm::class)
            ->call('add')
            ->set('name', 'Mirpur')
            ->set('latitude', 23.8223)
            ->set('longitude', 90.3654)
            ->set('radius', 6)
            ->call('save')
            ->assertHasNoErrors();

        $area = DeliveryArea::where('name', 'Mirpur')->firstOrFail();
        $this->assertSame(6.0, $area->radius_km);
    }

    public function test_an_area_with_no_pin_is_refused(): void
    {
        $this->asShopkeeper();

        Livewire::test(DeliveryAreaForm::class)
            ->call('add')
            ->set('name', 'Nowhere')
            ->call('save')
            ->assertHasErrors('latitude');

        $this->assertSame(0, DeliveryArea::count());
    }

    public function test_two_areas_cannot_share_a_name(): void
    {
        $this->area('Mirpur', $this->dhaka, 6);
        $this->asShopkeeper();

        Livewire::test(DeliveryAreaForm::class)
            ->call('add')
            ->set('name', 'Mirpur')
            ->set('latitude', 23.9)
            ->set('longitude', 90.4)
            ->set('radius', 4)
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(1, DeliveryArea::count());
    }

    public function test_an_area_still_used_by_a_product_cannot_be_removed(): void
    {
        $mirpur = $this->area('Mirpur', $this->dhaka, 6);
        $this->product('Ice cream', Product::AVAILABLE_AREAS, [$mirpur->id]);
        $this->asShopkeeper();

        Livewire::test(DeliveryAreaForm::class)->call('delete', $mirpur->id);

        $this->assertNotNull(DeliveryArea::find($mirpur->id));
    }

    public function test_an_area_nothing_uses_can_be_removed(): void
    {
        $spare = $this->area('Somewhere', $this->dhaka, 6);
        $this->asShopkeeper();

        Livewire::test(DeliveryAreaForm::class)->call('delete', $spare->id);

        $this->assertNull(DeliveryArea::find($spare->id));
    }

    /*
     * ----------------------------------------------- picking areas on a product
     */

    public function test_a_shopkeeper_picks_areas_on_a_product_instead_of_drawing_a_map(): void
    {
        $mirpur = $this->area('Mirpur', $this->dhaka, 8);
        $this->area('Uttara', $this->dhaka, 12);
        $this->asShopkeeper();

        $product = $this->product('Ice cream');

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSee('Only certain areas')
            ->assertSee('Mirpur')
            ->assertSee('Uttara')
            ->set('availability', Product::AVAILABLE_AREAS)
            ->set('delivery_area_ids', [$mirpur->id])
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame(Product::AVAILABLE_AREAS, $product->availability);
        $this->assertSame([$mirpur->id], $product->deliveryAreas()->pluck('delivery_areas.id')->all());
    }

    public function test_moving_a_product_off_areas_lets_go_of_them(): void
    {
        $mirpur = $this->area('Mirpur', $this->dhaka, 8);
        $this->asShopkeeper();

        $product = $this->product('Ice cream', Product::AVAILABLE_AREAS, [$mirpur->id]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('availability', Product::AVAILABLE_ANYWHERE)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $product->fresh()->deliveryAreas()->count());
    }

    public function test_a_product_cannot_be_tied_to_another_shops_area(): void
    {
        $other = Tenant::factory()->create(['currency' => 'BDT', 'country_code' => 'BD']);
        $theirs = Tenancy::run($other, fn () => DeliveryArea::create([
            'tenant_id' => $other->id,
            'name' => 'Theirs', 'latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 5,
        ]));

        $product = $this->product('Rice', Product::AVAILABLE_AREAS, [$theirs->id]);

        $this->assertSame(0, $product->deliveryAreas()->count());
    }
}
