<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Storefront\DeliveryAreas;
use App\Support\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Showing a customer only what can actually reach them.
 *
 * The shop draws one area. Products follow it, ignore it, or draw their own.
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

    protected function shopDeliversWithin(float $km, GeoPoint $from): void
    {
        $this->store->forceFill([
            'delivers_everywhere' => false,
            'delivery_latitude' => $from->latitude,
            'delivery_longitude' => $from->longitude,
            'delivery_radius_km' => $km,
        ])->save();

        Tenancy::set($this->store->fresh());
    }

    protected function product(string $name, array $attributes = []): Product
    {
        $variant = app(ProductService::class)->create([
            'name' => $name, 'regular_price' => '100', 'stock' => 10,
            'status' => Product::STATUS_ACTIVE, 'published_at' => now()->subDay(),
        ]);

        $product = $variant instanceof Product ? $variant : $variant->product;

        if ($attributes !== []) {
            $product->forceFill($attributes)->save();
        }

        return $product->fresh();
    }

    /**
     * @return array<int, string>
     */
    protected function seenFrom(?GeoPoint $customer): array
    {
        return Product::query()
            ->deliverableTo(Tenancy::current(), $customer)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function test_a_shop_that_delivers_everywhere_shows_everything(): void
    {
        $this->product('Rice');
        $this->product('Lentils');

        $this->assertSame(['Lentils', 'Rice'], $this->seenFrom($this->chittagong));
    }

    public function test_a_customer_outside_the_shops_area_sees_nothing_that_follows_it(): void
    {
        $this->shopDeliversWithin(10, $this->dhaka);

        $this->product('Rice');
        $this->product('Milk');

        $this->assertSame(['Milk', 'Rice'], $this->seenFrom($this->gulshan));
        $this->assertSame([], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_marked_anywhere_ignores_the_shops_area(): void
    {
        $this->shopDeliversWithin(10, $this->dhaka);

        $this->product('Rice');
        $this->product('Tea', ['availability' => Product::AVAILABLE_ANYWHERE]);

        $this->assertSame(['Tea'], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_can_draw_a_wider_area_than_the_shop(): void
    {
        $this->shopDeliversWithin(10, $this->dhaka);

        $this->product('Rice');
        $this->product('Cooking oil', [
            'availability' => Product::AVAILABLE_AREA,
            'latitude' => $this->dhaka->latitude,
            'longitude' => $this->dhaka->longitude,
            'radius_km' => 300,
        ]);

        $this->assertSame(['Cooking oil'], $this->seenFrom($this->chittagong));
        $this->assertSame(['Cooking oil', 'Rice'], $this->seenFrom($this->gulshan));
    }

    public function test_a_product_can_draw_a_narrower_area_than_the_shop(): void
    {
        $this->product('Rice');
        $this->product('Ice cream', [
            'availability' => Product::AVAILABLE_AREA,
            'latitude' => $this->dhaka->latitude,
            'longitude' => $this->dhaka->longitude,
            'radius_km' => 3,
        ]);

        $this->assertSame(['Ice cream', 'Rice'], $this->seenFrom($this->gulshan));
        $this->assertSame(['Rice'], $this->seenFrom($this->chittagong));
    }

    public function test_a_product_that_claimed_an_area_but_drew_none_follows_the_shop(): void
    {
        $this->shopDeliversWithin(10, $this->dhaka);

        $this->product('Bread', ['availability' => Product::AVAILABLE_AREA]);

        $this->assertSame(['Bread'], $this->seenFrom($this->gulshan));
        $this->assertSame([], $this->seenFrom($this->chittagong));
    }

    public function test_a_customer_who_has_not_said_where_they_are_sees_the_whole_shop(): void
    {
        $this->shopDeliversWithin(1, $this->dhaka);

        $this->product('Rice');
        $this->product('Milk');

        $this->assertSame(['Milk', 'Rice'], $this->seenFrom(null));
    }

    public function test_the_shopkeeper_is_told_in_plain_words_where_each_product_goes(): void
    {
        $this->shopDeliversWithin(10, $this->dhaka);
        $areas = app(DeliveryAreas::class);
        $shop = Tenancy::current();

        $this->assertSame(
            'Wherever the shop delivers, within 10 km',
            $areas->describe($this->product('Rice'), $shop),
        );

        $this->assertSame(
            'Delivered anywhere',
            $areas->describe($this->product('Tea', ['availability' => Product::AVAILABLE_ANYWHERE]), $shop),
        );

        $this->assertSame(
            'Its own area, within 3 km',
            $areas->describe($this->product('Ice cream', [
                'availability' => Product::AVAILABLE_AREA,
                'latitude' => $this->dhaka->latitude,
                'longitude' => $this->dhaka->longitude,
                'radius_km' => 3,
            ]), $shop),
        );
    }
}
