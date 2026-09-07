<?php

namespace App\Services\Catalogue;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\DeliveryArea;
use App\Models\InventoryLevel;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Support\HtmlSanitiser;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating and changing products.
 *
 * A product always has at least one thing that can be sold and counted: with
 * no choices that is the product itself, with choices it is each combination.
 */
class ProductService
{
    public function __construct(
        protected InventoryService $inventory,
        protected HtmlSanitiser $sanitiser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        // The shop's plan decides how many products it may have.
        Entitlements::ensureCanAdd('products', Product::count());

        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'description' => $this->sanitiser->clean($data['description'] ?? null),
                'short_description' => $this->plainText($data['short_description'] ?? null, 500),
                'status' => $data['status'] ?? Product::STATUS_DRAFT,
                'has_variants' => false,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'tags' => $this->cleanTags($data['tags'] ?? null),
                'video_url' => ($data['video_url'] ?? null) ?: null,
                'shipping_charge_minor' => $this->shippingMinor($data['shipping_charge'] ?? null),
                'published_at' => ($data['status'] ?? null) === Product::STATUS_ACTIVE ? now() : null,
                ...$this->deliveryArea($data),
            ]);

            $this->syncCategories($product, $data['category_ids'] ?? []);
            $this->syncDeliveryAreas($product, $data['delivery_area_ids'] ?? []);

            $variant = $this->createVariant($product, array_merge($this->pricing($data), [
                'cost_price' => $data['cost_price'] ?? null,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'weight_grams' => $data['weight_grams'] ?? null,
                'length_mm' => $data['length_mm'] ?? null,
                'width_mm' => $data['width_mm'] ?? null,
                'height_mm' => $data['height_mm'] ?? null,
                'is_default' => true,
                'position' => 0,
            ]));

            $level = $this->inventory->levelFor($variant);
            $level->update([
                'track_inventory' => $data['track_inventory'] ?? true,
                'allow_backorder' => $data['allow_backorder'] ?? false,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            ]);

            if (($data['stock'] ?? 0) > 0) {
                $this->inventory->setTo($variant, (int) $data['stock'], 'First stock');
            }

            return $product->load('variants');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $wasOnSale = $product->status === Product::STATUS_ACTIVE;
            $nowOnSale = ($data['status'] ?? $product->status) === Product::STATUS_ACTIVE;

            $product->fill([
                'brand_id' => $data['brand_id'] ?? $product->brand_id,
                'name' => $data['name'] ?? $product->name,
                'description' => array_key_exists('description', $data)
                    ? $this->sanitiser->clean($data['description'])
                    : $product->description,
                'status' => $data['status'] ?? $product->status,
                'meta_title' => $data['meta_title'] ?? $product->meta_title,
                'meta_description' => $data['meta_description'] ?? $product->meta_description,
                'short_description' => array_key_exists('short_description', $data)
                    ? $this->plainText($data['short_description'], 500)
                    : $product->short_description,
                'tags' => array_key_exists('tags', $data)
                    ? $this->cleanTags($data['tags'])
                    : $product->tags,
                'video_url' => array_key_exists('video_url', $data)
                    ? ($data['video_url'] ?: null)
                    : $product->video_url,
                'shipping_charge_minor' => array_key_exists('shipping_charge', $data)
                    ? $this->shippingMinor($data['shipping_charge'])
                    : $product->shipping_charge_minor,
            ]);

            if (array_key_exists('availability', $data)) {
                $product->fill($this->deliveryArea($data));
            }

            if (array_key_exists('delivery_area_ids', $data)) {
                $this->syncDeliveryAreas($product, $data['delivery_area_ids']);
            }

            if (array_key_exists('slug', $data) && $data['slug'] !== $product->slug) {
                $product->slug = $this->uniqueSlug($data['slug'], $product->id);
            }

            if (! $wasOnSale && $nowOnSale && $product->published_at === null) {
                $product->published_at = now();
            }

            $product->save();

            if (array_key_exists('category_ids', $data)) {
                $this->syncCategories($product, $data['category_ids']);
            }

            return $product;
        });
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $tenantId = Tenancy::id();

        $product->categories()->sync(
            collect($categoryIds)
                ->filter()
                ->mapWithKeys(fn ($id) => [(int) $id => ['tenant_id' => $tenantId]])
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVariant(Product $product, array $data): ProductVariant
    {
        $currency = Tenancy::current()->currency;
        $exponent = Tenancy::current()->currency_exponent;

        return $product->variants()->create([
            'tenant_id' => $product->tenant_id,
            'name' => $data['name'] ?? null,
            'sku' => $data['sku'] ?: null,
            'barcode' => $data['barcode'] ?? null,
            'price_minor' => $this->toMinor($data['price'] ?? '0', $currency, $exponent),
            'currency' => $currency,
            'currency_exponent' => $exponent,
            'compare_at_price_minor' => isset($data['compare_at_price']) && $data['compare_at_price'] !== '' && $data['compare_at_price'] !== null
                ? $this->toMinor($data['compare_at_price'], $currency, $exponent)
                : null,
            'cost_price_minor' => isset($data['cost_price']) && $data['cost_price'] !== '' && $data['cost_price'] !== null
                ? $this->toMinor($data['cost_price'], $currency, $exponent)
                : null,
            'weight_grams' => ($data['weight_grams'] ?? null) ?: null,
            'length_mm' => ($data['length_mm'] ?? null) ?: null,
            'width_mm' => ($data['width_mm'] ?? null) ?: null,
            'height_mm' => ($data['height_mm'] ?? null) ?: null,
            'position' => $data['position'] ?? 0,
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    /**
     * Turn a product with choices into one sellable line per combination.
     *
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     */
    public function setOptions(Product $product, array $options): Product
    {
        return DB::transaction(function () use ($product, $options) {
            $product->options()->delete();

            $valueIdsPerOption = [];

            foreach (array_values($options) as $position => $option) {
                $values = array_values(array_filter(array_map('trim', $option['values'])));

                if ($option['name'] === '' || $values === []) {
                    continue;
                }

                /** @var ProductOption $created */
                $created = $product->options()->create([
                    'tenant_id' => $product->tenant_id,
                    'name' => $option['name'],
                    'position' => $position,
                ]);

                $ids = [];

                foreach ($values as $valuePosition => $value) {
                    $ids[] = $created->values()->create([
                        'tenant_id' => $product->tenant_id,
                        'value' => $value,
                        'position' => $valuePosition,
                    ])->id;
                }

                $valueIdsPerOption[] = $ids;
            }

            if ($valueIdsPerOption === []) {
                $product->update(['has_variants' => false]);

                return $product->fresh(['variants', 'options']);
            }

            $this->buildVariants($product, $this->combinations($valueIdsPerOption));
            $product->update(['has_variants' => true]);

            return $product->fresh(['variants', 'options']);
        });
    }

    /**
     * Every combination of the choices, e.g. Small/Red, Small/Blue, Large/Red.
     *
     * @param  array<int, array<int, int>>  $valueIdsPerOption
     * @return array<int, array<int, int>>
     */
    protected function combinations(array $valueIdsPerOption): array
    {
        $combinations = [[]];

        foreach ($valueIdsPerOption as $valueIds) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($valueIds as $valueId) {
                    $next[] = [...$combination, $valueId];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * @param  array<int, array<int, int>>  $combinations
     */
    protected function buildVariants(Product $product, array $combinations): void
    {
        $template = $product->variants()->orderBy('position')->first();
        $tenantId = $product->tenant_id;

        $keep = [];

        foreach ($combinations as $position => $valueIds) {
            $existing = $product->variants()
                ->whereHas('optionValues', fn ($q) => $q->whereIn('product_option_values.id', $valueIds), '=', count($valueIds))
                ->first();

            $variant = $existing ?? $this->createVariant($product, [
                'price' => $template
                    ? (new Money($template->price_minor, $template->currency, $template->currency_exponent))->toDecimal()
                    : '0',
                'compare_at_price' => $template?->compare_at_price_minor === null
                    ? null
                    : (new Money($template->compare_at_price_minor, $template->currency, $template->currency_exponent))->toDecimal(),
                // What it costs, what it weighs and how big it is carry over.
                // Losing the cost here would quietly wreck the profit figures.
                'cost_price' => $template?->cost_price_minor === null
                    ? null
                    : (new Money($template->cost_price_minor, $template->currency, $template->currency_exponent))->toDecimal(),
                'weight_grams' => $template?->weight_grams,
                'length_mm' => $template?->length_mm,
                'width_mm' => $template?->width_mm,
                'height_mm' => $template?->height_mm,
                'sku' => null,
                'position' => $position,
                'is_default' => $position === 0,
            ]);

            $variant->update(['position' => $position, 'is_default' => $position === 0]);

            $variant->optionValues()->sync(
                collect($valueIds)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId]])->all()
            );

            $this->inventory->levelFor($variant);

            $keep[] = $variant->id;
        }

        // Combinations the shopkeeper no longer offers. Their stock row goes
        // too, or it would sit there for ever counting as sold out. The
        // history stays, because history is never rewritten.
        $product->variants()->whereNotIn('id', $keep)->get()->each(function (ProductVariant $gone) {
            InventoryLevel::where('product_variant_id', $gone->getKey())->delete();
            $gone->delete();
        });
    }

    /**
     * Work out what is actually charged.
     *
     * The shopkeeper enters a regular price and, if they are having a sale, a
     * discount price. What is charged is the discount when there is one, and
     * the regular price is then shown crossed out beside it.
     *
     * @param  array<string, mixed>  $data
     * @return array{price: string, compare_at_price: string|null}
     */
    public function pricing(array $data): array
    {
        if (! array_key_exists('regular_price', $data)) {
            return [
                'price' => (string) ($data['price'] ?? '0'),
                'compare_at_price' => $data['compare_at_price'] ?? null,
            ];
        }

        $currency = Tenancy::current()->currency;
        $exponent = Tenancy::current()->currency_exponent;

        $regular = (string) ($data['regular_price'] === '' || $data['regular_price'] === null ? '0' : $data['regular_price']);
        $discount = $data['discount_price'] ?? null;

        $hasDiscount = $discount !== null
            && $discount !== ''
            && is_numeric($discount)
            && $this->toMinor($discount, $currency, $exponent) > 0
            && $this->toMinor($discount, $currency, $exponent) < $this->toMinor($regular, $currency, $exponent);

        return $hasDiscount
            ? ['price' => (string) $discount, 'compare_at_price' => $regular]
            : ['price' => $regular, 'compare_at_price' => null];
    }

    /**
     * @param  array<int, string>|string|null  $tags
     * @return array<int, string>|null
     */
    /**
     * Where this product is delivered.
     *
     * Anything that is not one of the three known settings falls back to
     * "wherever the shop delivers", so a stray value can never quietly limit
     * who sees a product.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function deliveryArea(array $data): array
    {
        $availability = $data['availability'] ?? Product::AVAILABLE_SHOP;

        if (! in_array($availability, [
            Product::AVAILABLE_SHOP, Product::AVAILABLE_ANYWHERE, Product::AVAILABLE_AREAS,
        ], true)) {
            $availability = Product::AVAILABLE_SHOP;
        }

        return ['availability' => $availability];
    }

    /**
     * Tie a product to the shop's named areas, or to none of them.
     *
     * @param  array<int, int|string>  $areaIds
     */
    public function syncDeliveryAreas(Product $product, array $areaIds): void
    {
        $tenantId = Tenancy::id();

        // Only the shop's own areas. An id from anywhere else is dropped
        // rather than trusted.
        $mine = DeliveryArea::whereIn('id', collect($areaIds)->filter()->map(fn ($id) => (int) $id))
            ->pluck('id');

        $product->deliveryAreas()->sync(
            $mine->mapWithKeys(fn (int $id) => [$id => ['tenant_id' => $tenantId]])->all()
        );
    }

    protected function cleanTags(array|string|null $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        $list = is_string($tags) ? explode(',', $tags) : $tags;

        $clean = collect($list)
            ->map(fn ($tag) => trim(strip_tags((string) $tag)))
            ->filter()
            ->map(fn (string $tag) => mb_substr($tag, 0, 40))
            ->unique()
            ->take(20)
            ->values()
            ->all();

        return $clean === [] ? null : $clean;
    }

    protected function plainText(?string $value, int $limit): ?string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, $limit);
    }

    protected function shippingMinor(string|int|float|null $charge): ?int
    {
        if ($charge === null || $charge === '' || ! is_numeric($charge)) {
            return null;
        }

        return $this->toMinor($charge, Tenancy::current()->currency, Tenancy::current()->currency_exponent);
    }

    protected function toMinor(string|int|float $amount, string $currency, int $exponent): int
    {
        return Money::fromDecimal($amount === '' ? '0' : $amount, $currency, $exponent)->minor;
    }

    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'product';
        $slug = $base;
        $suffix = 1;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
