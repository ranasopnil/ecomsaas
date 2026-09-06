<?php

namespace App\Services\Demo;

use App\Facades\Tenancy;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\Catalogue\ImageService;
use App\Services\Catalogue\ProductService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills a shop with believable sample data so the screens can be judged with
 * something in them.
 *
 * Everything it creates is marked, and removing it only removes what is
 * marked. Nothing the shopkeeper typed is ever touched.
 */
class SampleShop
{
    public const BATCH = 'sample';

    /** @var array<int, array{name: string, category: string, brand: string, regular: string, cost: string, discount?: string, options?: array<string, array<int, string>>, stock: int, low?: int, tags: string, short: string, body: string}> */
    protected const CATALOGUE = [
        ['name' => 'Cotton Panjabi', 'category' => 'Panjabi', 'brand' => 'Aarong', 'regular' => '1850', 'cost' => '1100',
            'options' => ['Size' => ['M', 'L', 'XL']], 'stock' => 14, 'low' => 5,
            'tags' => 'panjabi, cotton, eid, menswear', 'short' => 'Soft handloom cotton panjabi with a regular fit.',
            'body' => '<h2>About this panjabi</h2><p>Woven from <strong>handloom cotton</strong> and finished with a simple neckline.</p><ul><li>Regular fit</li><li>Machine washable</li></ul>'],
        ['name' => 'Embroidered Panjabi', 'category' => 'Panjabi', 'brand' => 'Kay Kraft', 'regular' => '3400', 'cost' => '2050',
            'discount' => '2950', 'options' => ['Size' => ['M', 'L', 'XL']], 'stock' => 9, 'low' => 4,
            'tags' => 'panjabi, embroidery, eid, festive', 'short' => 'Hand embroidered panjabi for Eid and weddings.',
            'body' => '<h2>Hand embroidered</h2><p>Chest panel embroidered by hand. Each piece is slightly different.</p>'],
        ['name' => 'Half Sleeve Shirt', 'category' => 'Shirts', 'brand' => 'Yellow', 'regular' => '1250', 'cost' => '700',
            'options' => ['Size' => ['S', 'M', 'L'], 'Colour' => ['White', 'Sky']], 'stock' => 7, 'low' => 4,
            'tags' => 'shirt, casual, summer', 'short' => 'Light cotton shirt for warm days.',
            'body' => '<p>Breathable cotton, cut a little loose through the body.</p>'],
        ['name' => 'Formal Full Sleeve Shirt', 'category' => 'Shirts', 'brand' => 'Yellow', 'regular' => '1950', 'cost' => '1150',
            'options' => ['Size' => ['M', 'L', 'XL']], 'stock' => 11, 'tags' => 'shirt, formal, office',
            'short' => 'Crisp office shirt that holds its press.', 'body' => '<p>Poplin cotton with a stiffened collar.</p>'],
        ['name' => 'Jamdani Saree', 'category' => 'Saree', 'brand' => 'Aarong', 'regular' => '9500', 'cost' => '5800',
            'stock' => 4, 'low' => 3, 'tags' => 'saree, jamdani, handloom, wedding',
            'short' => 'Handwoven Jamdani from Rupganj, six and a half yards.',
            'body' => '<h2>Woven by hand</h2><p>Traditional Jamdani takes weeks on the loom. No two are the same.</p><table><tbody><tr><th>Length</th><td>6.5 yards</td></tr><tr><th>Blouse piece</th><td>Included</td></tr></tbody></table>'],
        ['name' => 'Soft Cotton Saree', 'category' => 'Saree', 'brand' => 'Rang Bangladesh', 'regular' => '3200', 'cost' => '1900',
            'discount' => '2750', 'stock' => 16, 'tags' => 'saree, cotton, daily',
            'short' => 'An everyday cotton saree that gets softer with washing.', 'body' => '<p>Light enough for a full day in the heat.</p>'],
        ['name' => 'Printed Kurti', 'category' => 'Kurti', 'brand' => 'Deshal', 'regular' => '1750', 'cost' => '980',
            'options' => ['Size' => ['S', 'M', 'L', 'XL']], 'stock' => 21, 'low' => 6,
            'tags' => 'kurti, printed, womenswear', 'short' => 'Block printed kurti with side slits.',
            'body' => '<p>Block printed by hand in Narayanganj.</p>'],
        ['name' => 'Palazzo Trousers', 'category' => 'Kurti', 'brand' => 'Deshal', 'regular' => '1150', 'cost' => '600',
            'options' => ['Size' => ['M', 'L']], 'stock' => 0, 'tags' => 'palazzo, womenswear',
            'short' => 'Wide legged trousers in flowing viscose.', 'body' => '<p>Elasticated waist, falls to the ankle.</p>'],
        ['name' => 'Nakshi Kantha Bedcover', 'category' => 'Bedding', 'brand' => 'Aarong', 'regular' => '4800', 'cost' => '2900',
            'stock' => 6, 'low' => 3, 'tags' => 'kantha, bedding, handmade, home',
            'short' => 'Hand stitched kantha bedcover, double bed size.',
            'body' => '<h2>Stitched by hand</h2><p>Layers of cotton held together with running stitch, in the Nakshi Kantha tradition.</p>'],
        ['name' => 'Cotton Bed Sheet Set', 'category' => 'Bedding', 'brand' => 'Rang Bangladesh', 'regular' => '2600', 'cost' => '1550',
            'stock' => 12, 'tags' => 'bedsheet, bedding, home', 'short' => 'Bed sheet with two pillow covers.',
            'body' => '<p>Fits a standard double bed. Colour holds after washing.</p>'],
        ['name' => 'Terracotta Vase', 'category' => 'Decor', 'brand' => 'Kay Kraft', 'regular' => '1200', 'cost' => '620',
            'stock' => 2, 'low' => 4, 'tags' => 'terracotta, vase, decor, home',
            'short' => 'Hand thrown terracotta vase from Dhamrai.', 'body' => '<p>Unglazed, so it darkens a little with time.</p>'],
        ['name' => 'Jute Storage Basket', 'category' => 'Decor', 'brand' => 'Kay Kraft', 'regular' => '850', 'cost' => '400',
            'stock' => 18, 'tags' => 'jute, basket, storage, home', 'short' => 'Woven jute basket that keeps its shape.',
            'body' => '<p>Made from Bangladeshi jute. Holds about twelve litres.</p>'],
        ['name' => 'Handloom Gamcha', 'category' => 'Decor', 'brand' => 'Rang Bangladesh', 'regular' => '380', 'cost' => '170',
            'stock' => 40, 'tags' => 'gamcha, handloom, cotton', 'short' => 'The classic checked cotton gamcha.',
            'body' => '<p>Dries quickly and softens with every wash.</p>'],
        ['name' => 'Leather Sandal', 'category' => 'Menswear', 'brand' => 'Deshal', 'regular' => '2400', 'cost' => '1500',
            'options' => ['Size' => ['39', '40', '41', '42']], 'stock' => 3, 'low' => 4,
            'tags' => 'sandal, leather, footwear', 'short' => 'Full grain leather sandal with a stitched sole.',
            'body' => '<p>Leather softens to the shape of the foot after a week or two.</p>'],
        ['name' => 'Boys Panjabi Set', 'category' => 'Panjabi', 'brand' => 'Aarong', 'regular' => '1450', 'cost' => '820',
            'options' => ['Size' => ['4Y', '6Y', '8Y']], 'stock' => 13, 'tags' => 'panjabi, kids, eid',
            'short' => 'Panjabi and pyjama set for boys.', 'body' => '<p>Cotton, with a matching pyjama.</p>'],
        ['name' => 'Cotton Tote Bag', 'category' => 'Decor', 'brand' => 'Yellow', 'regular' => '650', 'cost' => '290',
            'discount' => '550', 'stock' => 25, 'tags' => 'tote, bag, cotton, reusable',
            'short' => 'Sturdy cotton tote for the market.', 'body' => '<p>Double stitched handles that take a full load.</p>'],
        ['name' => 'Ceramic Mug', 'category' => 'Decor', 'brand' => 'Kay Kraft', 'regular' => '450', 'cost' => '210',
            'stock' => 31, 'tags' => 'mug, ceramic, kitchen', 'short' => 'Glazed ceramic mug, three hundred millilitres.',
            'body' => '<p>Safe in the dishwasher and the microwave.</p>'],
        ['name' => 'Wall Hanging', 'category' => 'Decor', 'brand' => 'Rang Bangladesh', 'regular' => '1900', 'cost' => '1050',
            'stock' => 5, 'low' => 5, 'tags' => 'wall hanging, decor, handmade',
            'short' => 'Woven wall hanging with a wooden rod.', 'body' => '<p>About sixty centimetres across.</p>'],
        ['name' => 'Silk Scarf', 'category' => 'Womenswear', 'brand' => 'Aarong', 'regular' => '1650', 'cost' => '900',
            'stock' => 0, 'tags' => 'scarf, silk, gift', 'short' => 'Light silk scarf with a hand rolled edge.',
            'body' => '<p>Rajshahi silk, hand rolled at the edges.</p>'],
        ['name' => 'House Slippers', 'category' => 'Menswear', 'brand' => 'Deshal', 'regular' => '780', 'cost' => '390',
            'options' => ['Size' => ['M', 'L']], 'stock' => 22, 'tags' => 'slippers, home, footwear',
            'short' => 'Soft soled slippers for indoors.', 'body' => '<p>Quiet on tiles, warm on cold mornings.</p>'],
    ];

    /** @var array<string, string|null> category => the one it sits inside */
    protected const CATEGORIES = [
        'Menswear' => null,
        'Panjabi' => 'Menswear',
        'Shirts' => 'Menswear',
        'Womenswear' => null,
        'Saree' => 'Womenswear',
        'Kurti' => 'Womenswear',
        'Home' => null,
        'Bedding' => 'Home',
        'Decor' => 'Home',
    ];

    protected const BRANDS = ['Aarong', 'Kay Kraft', 'Yellow', 'Deshal', 'Rang Bangladesh'];

    public function __construct(
        protected ProductService $products,
        protected ImageService $images,
    ) {}

    /**
     * @return array{products: int, variants: int, movements: int}
     */
    public function fill(Tenant $store, bool $withPhotos = true): array
    {
        return Tenancy::run($store, function () use ($withPhotos) {
            mt_srand(20260906);

            $categories = $this->categories();
            $brands = $this->brands();

            $made = 0;
            $variants = 0;
            $movements = 0;

            foreach (self::CATALOGUE as $definition) {
                if (Product::where('name', $definition['name'])->exists()) {
                    continue;
                }

                $product = $this->products->create([
                    'name' => $definition['name'],
                    'description' => $definition['body'],
                    'short_description' => $definition['short'],
                    'tags' => $definition['tags'],
                    'brand_id' => $brands[$definition['brand']]->id,
                    'category_ids' => [$categories[$definition['category']]->id],
                    'status' => Product::STATUS_ACTIVE,
                    'regular_price' => $definition['regular'],
                    'discount_price' => $definition['discount'] ?? null,
                    'cost_price' => $definition['cost'],
                    'weight_grams' => mt_rand(180, 1400),
                    'low_stock_threshold' => $definition['low'] ?? null,
                ]);

                $product->update(['demo_batch' => self::BATCH]);

                if (isset($definition['options'])) {
                    $product = $this->products->setOptions($product, collect($definition['options'])
                        ->map(fn (array $values, string $name) => ['name' => $name, 'values' => $values])
                        ->values()
                        ->all());
                }

                foreach ($product->fresh('variants')->variants as $index => $variant) {
                    $variants++;
                    $movements += $this->stockHistory($variant, $definition, $index);
                }

                if ($withPhotos) {
                    $this->photo($product, $made);
                }

                $made++;
            }

            return ['products' => $made, 'variants' => $variants, 'movements' => $movements];
        });
    }

    /**
     * Remove only what was put in as sample data.
     *
     * @return array{products: int}
     */
    public function remove(Tenant $store): array
    {
        return Tenancy::run($store, function () {
            $products = Product::withTrashed()->where('demo_batch', self::BATCH)->get();
            $removed = 0;

            foreach ($products as $product) {
                foreach ($product->images()->get() as $image) {
                    $this->images->delete($image);
                }

                // The database clears the variants, stock and history that hang
                // off the product.
                DB::transaction(fn () => $product->forceDelete());
                $removed++;
            }

            Category::where('demo_batch', self::BATCH)->get()
                ->each(fn (Category $category) => $category->products()->count() === 0 ? $category->delete() : null);

            Brand::where('demo_batch', self::BATCH)->get()
                ->each(fn (Brand $brand) => $brand->products()->count() === 0 ? $brand->delete() : null);

            return ['products' => $removed];
        });
    }

    /**
     * @return array<string, Category>
     */
    protected function categories(): array
    {
        $made = [];

        foreach (self::CATEGORIES as $name => $parent) {
            $made[$name] = Category::firstOrCreate(
                ['slug' => str($name)->slug()->value()],
                [
                    'name' => $name,
                    'parent_id' => $parent ? $made[$parent]->id : null,
                    'is_active' => true,
                    'demo_batch' => self::BATCH,
                ],
            );
        }

        return $made;
    }

    /**
     * @return array<string, Brand>
     */
    protected function brands(): array
    {
        $made = [];

        foreach (self::BRANDS as $name) {
            $made[$name] = Brand::firstOrCreate(
                ['slug' => str($name)->slug()->value()],
                ['name' => $name, 'is_active' => true, 'demo_batch' => self::BATCH],
            );
        }

        return $made;
    }

    /**
     * A fortnight of comings and goings, ending at the stock the shop has now.
     *
     * The history and the count on the shelf agree, because the count is what
     * the history adds up to.
     */
    protected function stockHistory(ProductVariant $variant, array $definition, int $index): int
    {
        $target = max(0, (int) round(($definition['stock'] ?? 10) / max(1, $index + 1)) + ($index === 0 ? 0 : mt_rand(0, 4)));
        $running = $target + mt_rand(6, 24);
        $rows = [];

        // Opening stock, two weeks ago.
        $rows[] = [
            'change' => $running,
            'after' => $running,
            'reason' => InventoryMovement::REASON_RECEIVED,
            'note' => 'Opening stock',
            'at' => Carbon::today()->subDays(14)->addHours(9),
        ];

        // Sales and deliveries in between.
        for ($day = 13; $day >= 1; $day--) {
            if (mt_rand(1, 100) > 55) {
                continue;
            }

            $isDelivery = mt_rand(1, 100) > 78;
            $change = $isDelivery ? mt_rand(4, 15) : -mt_rand(1, 4);

            if ($running + $change < 0) {
                continue;
            }

            $running += $change;

            $rows[] = [
                'change' => $change,
                'after' => $running,
                'reason' => $isDelivery ? InventoryMovement::REASON_RECEIVED : InventoryMovement::REASON_SOLD,
                'note' => $isDelivery ? 'Delivery received' : 'Sold in the shop',
                'at' => Carbon::today()->subDays($day)->addHours(mt_rand(9, 20)),
            ];
        }

        // A stock take today brings it to what is on the shelf.
        if ($running !== $target) {
            $rows[] = [
                'change' => $target - $running,
                'after' => $target,
                'reason' => InventoryMovement::REASON_STOCK_TAKE,
                'note' => 'Counted today',
                'at' => Carbon::now()->subHours(2),
            ];
        }

        foreach ($rows as $row) {
            // The time is set before the row is first written, not changed
            // afterwards, so the rule that history cannot be edited holds.
            $movement = new InventoryMovement([
                'product_variant_id' => $variant->getKey(),
                'quantity_change' => $row['change'],
                'available_after' => $row['after'],
                'reason' => $row['reason'],
                'note' => $row['note'],
            ]);

            $movement->created_at = $row['at'];
            $movement->save();
        }

        InventoryLevel::updateOrCreate(
            ['product_variant_id' => $variant->getKey()],
            [
                'available' => $target,
                'reserved' => 0,
                'track_inventory' => true,
                'low_stock_threshold' => $definition['low'] ?? null,
            ],
        );

        return count($rows);
    }

    /**
     * A plain, tidy picture made here rather than fetched from anywhere.
     */
    protected function photo(Product $product, int $seed): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $size = 1000;
        $canvas = imagecreatetruecolor($size, $size);

        $hue = ($seed * 37) % 360;
        [$r, $g, $b] = $this->hsl($hue, 0.32, 0.86);
        [$r2, $g2, $b2] = $this->hsl($hue, 0.38, 0.62);

        // A soft top to bottom wash.
        for ($y = 0; $y < $size; $y++) {
            $mix = $y / $size;
            $line = imagecolorallocate(
                $canvas,
                (int) ($r + ($r2 - $r) * $mix),
                (int) ($g + ($g2 - $g) * $mix),
                (int) ($b + ($b2 - $b) * $mix),
            );
            imageline($canvas, 0, $y, $size, $y, $line);
        }

        // The product's initials, quietly.
        $initials = collect(explode(' ', $product->name))->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');

        $ink = imagecolorallocatealpha($canvas, 255, 255, 255, 40);
        imagestring($canvas, 5, (int) ($size / 2) - 20, (int) ($size / 2) - 8, $initials, $ink);

        $path = sys_get_temp_dir().'/sample-'.$product->id.'.jpg';
        imagejpeg($canvas, $path, 88);

        $this->images->store($product, new UploadedFile($path, str($product->name)->slug().'.jpg', 'image/jpeg', null, true));

        @unlink($path);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function hsl(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
