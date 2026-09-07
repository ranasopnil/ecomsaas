<?php

namespace App\Livewire\Admin;

use App\Exceptions\LimitReached;
use App\Facades\Tenancy;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalogue\ImageService;
use App\Services\Catalogue\InventoryService;
use App\Services\Catalogue\ProductService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class ProductForm extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $short_description = '';

    /** What the price is the price of: 'per kg', 'dozen', '500 g pack'. */
    public string $unit = '';

    public string $tags = '';

    public string $video_url = '';

    public ?int $brand_id = null;

    /** @var array<int, int> */
    public array $category_ids = [];

    public string $status = Product::STATUS_DRAFT;

    /** Published yes or no. Putting a product away is a separate action. */
    public bool $is_published = false;

    public string $regular_price = '';

    public string $discount_price = '';

    public string $sku = '';

    public string $cost_price = '';

    public string $shipping_charge = '';

    public string $weight_grams = '';

    public string $length_mm = '';

    public string $width_mm = '';

    public string $height_mm = '';

    public string $stock = '0';

    public bool $track_inventory = true;

    public bool $allow_backorder = false;

    public string $low_stock_threshold = '';

    /** @var array<int, array{name: string, values: string}> */
    public array $options = [];

    /** @var array<int, array{regular: string, discount: string, cost: string, sku: string, stock: string, weight: string}> */
    public array $variantRows = [];

    /** Photos waiting to be uploaded. */
    /** 'shop', 'anywhere' or 'areas' — see App\Models\Product. */
    public string $availability = Product::AVAILABLE_SHOP;

    /** The shop's own delivery areas this product is tied to. */
    public array $delivery_area_ids = [];

    public array $newPhotos = [];

    /** Which combination a photo belongs to, or nothing for the whole product. */
    public ?int $photoForVariant = null;

    /** The photo that just changed, so only that tile is highlighted. */
    public ?int $justTouchedPhoto = null;

    public function mount(?Product $product = null): void
    {
        if (! $product?->exists) {
            return;
        }

        $this->product = $product->load(['variants.inventory', 'options.values', 'categories']);
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->description = (string) $product->description;
        $this->brand_id = $product->brand_id;
        $this->category_ids = $product->categories->pluck('id')->all();
        $this->status = $product->status;
        $this->is_published = $product->status === Product::STATUS_ACTIVE;
        $this->short_description = (string) $product->short_description;
        $this->unit = (string) $product->unit;
        $this->tags = implode(', ', $product->tags ?? []);
        $this->video_url = (string) $product->video_url;
        $this->shipping_charge = $product->shipping_charge_minor === null
            ? ''
            : (new Money($product->shipping_charge_minor, $product->defaultVariant()?->currency ?? 'BDT', Tenancy::current()->currency_exponent))->toDecimal();

        $this->availability = $product->availability ?: Product::AVAILABLE_SHOP;
        $this->delivery_area_ids = $product->deliveryAreas()->pluck('delivery_areas.id')->all();

        $variant = $product->defaultVariant();

        if ($variant) {
            // What is charged is the discount when there is one, so the higher
            // 'was' figure is the regular price.
            $this->regular_price = $variant->isDiscounted()
                ? (new Money($variant->compare_at_price_minor, $variant->currency, $variant->currency_exponent))->toDecimal()
                : $variant->price->toDecimal();
            $this->discount_price = $variant->isDiscounted() ? $variant->price->toDecimal() : '';
            $this->weight_grams = (string) ($variant->weight_grams ?? '');
            $this->length_mm = (string) ($variant->length_mm ?? '');
            $this->width_mm = (string) ($variant->width_mm ?? '');
            $this->height_mm = (string) ($variant->height_mm ?? '');
            $this->sku = (string) $variant->sku;
            $this->cost_price = $variant->cost_price_minor !== null
                ? (new Money($variant->cost_price_minor, $variant->currency, $variant->currency_exponent))->toDecimal()
                : '';

            $level = $variant->inventory;
            $this->stock = (string) ($level?->available ?? 0);
            $this->track_inventory = $level?->track_inventory ?? true;
            $this->allow_backorder = $level?->allow_backorder ?? false;
            $this->low_stock_threshold = (string) ($level?->low_stock_threshold ?? '');
        }

        foreach ($product->options as $option) {
            $this->options[] = ['name' => $option->name, 'values' => $option->values->pluck('value')->implode(', ')];
        }

        $this->loadVariantRows();
    }

    protected function loadVariantRows(): void
    {
        $this->variantRows = [];

        if (! $this->product?->has_variants) {
            return;
        }

        foreach ($this->product->fresh(['variants.inventory'])->variants as $variant) {
            $this->variantRows[$variant->id] = [
                'regular' => $variant->isDiscounted()
                    ? (new Money($variant->compare_at_price_minor, $variant->currency, $variant->currency_exponent))->toDecimal()
                    : $variant->price->toDecimal(),
                'discount' => $variant->isDiscounted() ? $variant->price->toDecimal() : '',
                'weight' => (string) ($variant->weight_grams ?? ''),
                'cost' => $variant->cost_price_minor !== null
                    ? (new Money($variant->cost_price_minor, $variant->currency, $variant->currency_exponent))->toDecimal()
                    : '',
                'sku' => (string) $variant->sku,
                'stock' => (string) ($variant->inventory?->available ?? 0),
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'brand_id' => ['nullable', 'integer'],
            'category_ids.*' => ['integer'],
            'status' => ['required', Rule::in([Product::STATUS_DRAFT, Product::STATUS_ACTIVE, Product::STATUS_ARCHIVED])],
            'short_description' => ['nullable', 'string', 'max:500'],
            'unit' => ['nullable', 'string', 'max:40'],
            'tags' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'shipping_charge' => ['nullable', 'numeric', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'length_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'width_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'height_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'sku' => ['nullable', 'string', 'max:100'],
            'stock' => ['required', 'integer'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'options.*.name' => ['nullable', 'string', 'max:60'],
            'options.*.values' => ['nullable', 'string', 'max:500'],
            'availability' => ['required', Rule::in([
                Product::AVAILABLE_SHOP, Product::AVAILABLE_ANYWHERE, Product::AVAILABLE_AREAS,
            ])],
            'delivery_area_ids' => ['array'],
            'delivery_area_ids.*' => ['integer'],
        ];
    }

    public function addOption(): void
    {
        if (count($this->options) >= 3) {
            $this->dispatch('toast', ['text' => 'Three variants is the most a product can have.', 'tone' => 'bad']);

            return;
        }

        $this->options[] = ['name' => '', 'values' => ''];
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function save()
    {
        $this->validate();

        $this->assertDecimalsAllowed('regular_price', $this->regular_price);
        $this->assertDecimalsAllowed('discount_price', $this->discount_price);
        $this->assertDecimalsAllowed('cost_price', $this->cost_price);
        $this->assertDecimalsAllowed('shipping_charge', $this->shipping_charge);

        if ($this->discount_price !== '' && is_numeric($this->discount_price) && is_numeric($this->regular_price)
            && (float) $this->discount_price >= (float) $this->regular_price) {
            $this->addError('discount_price', 'The discount price has to be lower than the regular price.');
        }

        if ($this->video_url !== '' && ! $this->looksLikeYoutube($this->video_url)) {
            $this->addError('video_url', 'Paste a YouTube link, for example https://www.youtube.com/watch?v=xxxxxxxxxxx');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $products = app(ProductService::class);

        // Archived products keep that state; otherwise the yes/no switch decides.
        $status = $this->status === Product::STATUS_ARCHIVED
            ? Product::STATUS_ARCHIVED
            : ($this->is_published ? Product::STATUS_ACTIVE : Product::STATUS_DRAFT);

        $this->status = $status;

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'short_description' => $this->short_description ?: null,
            'unit' => $this->unit ?: null,
            'brand_id' => $this->brand_id ?: null,
            'category_ids' => $this->category_ids,
            'status' => $status,
            'tags' => $this->tags,
            'video_url' => $this->video_url ?: null,
            'shipping_charge' => $this->shipping_charge === '' ? null : $this->shipping_charge,
            'regular_price' => $this->regular_price,
            'discount_price' => $this->discount_price ?: null,
            'cost_price' => $this->cost_price ?: null,
            'weight_grams' => $this->weight_grams === '' ? null : (int) $this->weight_grams,
            'length_mm' => $this->length_mm === '' ? null : (int) $this->length_mm,
            'width_mm' => $this->width_mm === '' ? null : (int) $this->width_mm,
            'height_mm' => $this->height_mm === '' ? null : (int) $this->height_mm,
            'sku' => $this->sku ?: null,
            'stock' => (int) $this->stock,
            'track_inventory' => $this->track_inventory,
            'allow_backorder' => $this->allow_backorder,
            'low_stock_threshold' => $this->low_stock_threshold === '' ? null : (int) $this->low_stock_threshold,
            'availability' => $this->availability,
            // Areas only mean anything when the product is tied to them.
            'delivery_area_ids' => $this->availability === Product::AVAILABLE_AREAS
                ? $this->delivery_area_ids
                : [],
        ];

        if ($this->slug !== '') {
            $data['slug'] = $this->slug;
        }

        try {
            if ($this->product === null) {
                $this->product = $products->create($data);
            } else {
                $products->update($this->product, $data);
                $this->updateDefaultVariant();
            }
        } catch (LimitReached $e) {
            $this->addError('name', $e->getMessage());

            return null;
        }

        $this->applyOptions($products);

        session()->flash('status', "{$this->product->name} was saved.");

        return $this->redirectRoute('admin.products.edit', ['product' => $this->product], navigate: true);
    }

    protected function applyOptions(ProductService $products): void
    {
        $given = collect($this->options)
            ->map(fn ($option) => [
                'name' => trim($option['name']),
                'values' => array_filter(array_map('trim', explode(',', $option['values']))),
            ])
            ->filter(fn ($option) => $option['name'] !== '' && $option['values'] !== [])
            ->values()
            ->all();

        if ($given === [] && ! $this->product->has_variants) {
            return;
        }

        $products->setOptions($this->product, $given);
        $this->product = $this->product->fresh(['variants.inventory', 'options.values']);
    }

    protected function updateDefaultVariant(): void
    {
        $variant = $this->product->defaultVariant();

        if ($variant === null) {
            return;
        }

        $currency = Tenancy::current()->currency;
        $exponent = Tenancy::current()->currency_exponent;

        $pricing = app(ProductService::class)->pricing([
            'regular_price' => $this->regular_price,
            'discount_price' => $this->discount_price ?: null,
        ]);

        $variant->update([
            'price_minor' => Money::fromDecimal($pricing['price'], $currency, $exponent)->minor,
            'compare_at_price_minor' => $pricing['compare_at_price'] === null
                ? null
                : Money::fromDecimal($pricing['compare_at_price'], $currency, $exponent)->minor,
            'cost_price_minor' => $this->cost_price === ''
                ? null
                : Money::fromDecimal($this->cost_price, $currency, $exponent)->minor,
            'sku' => $this->sku ?: null,
            'weight_grams' => $this->weight_grams === '' ? null : (int) $this->weight_grams,
            'length_mm' => $this->length_mm === '' ? null : (int) $this->length_mm,
            'width_mm' => $this->width_mm === '' ? null : (int) $this->width_mm,
            'height_mm' => $this->height_mm === '' ? null : (int) $this->height_mm,
        ]);

        $inventory = app(InventoryService::class);
        $level = $inventory->levelFor($variant);

        $level->update([
            'track_inventory' => $this->track_inventory,
            'allow_backorder' => $this->allow_backorder,
            'low_stock_threshold' => $this->low_stock_threshold === '' ? null : (int) $this->low_stock_threshold,
        ]);

        if (! $this->product->has_variants && (int) $this->stock !== $level->available) {
            $inventory->setTo($variant, (int) $this->stock, 'Changed on the product page');
        }
    }

    /**
     * Save the price, code and count for each combination.
     */
    public function saveVariants(): void
    {
        $currency = Tenancy::current()->currency;
        $exponent = Tenancy::current()->currency_exponent;
        $inventory = app(InventoryService::class);

        foreach ($this->product->fresh('variants')->variants as $variant) {
            $row = $this->variantRows[$variant->id] ?? null;

            if ($row === null) {
                continue;
            }

            if (! is_numeric($row['regular'])) {
                $this->addError("variantRows.{$variant->id}.regular", 'Enter a price.');

                continue;
            }

            if (($row['discount'] ?? '') !== '' && is_numeric($row['discount'])
                && (float) $row['discount'] >= (float) $row['regular']) {
                $this->addError("variantRows.{$variant->id}.discount", 'Must be lower than the regular price.');

                continue;
            }

            $pricing = app(ProductService::class)->pricing([
                'regular_price' => $row['regular'],
                'discount_price' => $row['discount'] ?? null,
            ]);

            $variant->update([
                'price_minor' => Money::fromDecimal($pricing['price'], $currency, $exponent)->minor,
                'compare_at_price_minor' => $pricing['compare_at_price'] === null
                    ? null
                    : Money::fromDecimal($pricing['compare_at_price'], $currency, $exponent)->minor,
                'cost_price_minor' => ($row['cost'] ?? '') === '' || ! is_numeric($row['cost'])
                    ? null
                    : Money::fromDecimal($row['cost'], $currency, $exponent)->minor,
                'sku' => $row['sku'] ?: null,
                'weight_grams' => ($row['weight'] ?? '') === '' ? null : (int) $row['weight'],
            ]);

            $level = $inventory->levelFor($variant);

            if ((int) $row['stock'] !== $level->available) {
                $inventory->setTo($variant, (int) $row['stock'], 'Changed on the product page');
            }
        }

        $this->product = $this->product->fresh(['variants.inventory', 'options.values']);
        $this->loadVariantRows();
        $this->dispatch('toast', ['text' => 'The variant prices and counts were saved.', 'tone' => 'ok']);
    }

    /**
     * Photos are saved as soon as they are chosen, so the shopkeeper sees them
     * straight away rather than after saving the whole page.
     */
    public function updatedNewPhotos(): void
    {
        if ($this->product === null) {
            $this->addError('newPhotos', 'Save the product first, then add its photos.');
            $this->newPhotos = [];

            return;
        }

        $this->validate(['newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);

        $images = app(ImageService::class);

        foreach ($this->newPhotos as $photo) {
            try {
                $images->store($this->product, $photo, $this->photoForVariant);
            } catch (LimitReached $e) {
                $this->addError('newPhotos', $e->getMessage());
                break;
            }
        }

        $this->newPhotos = [];
        $this->product = $this->product->fresh(['images', 'variants.inventory', 'options.values']);
        $this->dispatch('toast', ['text' => 'Photo added.', 'tone' => 'ok']);
    }

    public function deletePhoto(int $imageId): void
    {
        $image = ProductImage::findOrFail($imageId);

        app(ImageService::class)->delete($image);

        $this->product = $this->product->fresh(['images', 'variants.inventory', 'options.values']);
        $this->dispatch('toast', ['text' => 'Photo removed.', 'tone' => 'ok']);
    }

    public function makePhotoPrimary(int $imageId): void
    {
        app(ImageService::class)->makePrimary(ProductImage::findOrFail($imageId));

        $this->product = $this->product->fresh(['images']);
        $this->justTouchedPhoto = $imageId;
        $this->dispatch('toast', ['text' => 'That photo is now the main one.', 'tone' => 'ok']);
    }

    /**
     * Attach a photo to one combination, or to the product as a whole.
     */
    public function assignPhoto(int $imageId, ?string $variantId): void
    {
        $image = ProductImage::findOrFail($imageId);

        $image->update(['product_variant_id' => $variantId === '' || $variantId === null ? null : (int) $variantId]);

        $this->product = $this->product->fresh(['images']);
        $this->justTouchedPhoto = $imageId;
        $this->dispatch('toast', ['text' => 'Photo updated.', 'tone' => 'ok']);
    }

    protected function looksLikeYoutube(string $url): bool
    {
        return (bool) preg_match(
            '~^https?://(www\.)?(youtube\.com/(watch\?|embed/|shorts/)|youtu\.be/)~i',
            trim($url)
        );
    }

    protected function assertDecimalsAllowed(string $field, string $value): void
    {
        if ($value === '' || Tenancy::current()->currency_exponent > 0) {
            return;
        }

        if (str_contains($value, '.')) {
            $this->addError($field, Tenancy::current()->currency.' has no decimal places. Enter a whole number.');
        }
    }

    public function render()
    {
        $images = $this->product?->images()->orderBy('position')->get() ?? collect();

        $deliveryAreas = DeliveryArea::orderBy('position')->orderBy('id')->get();

        return view('livewire.admin.product-form', [
            'deliveryAreas' => $deliveryAreas,
            'shopDeliversEverywhere' => $deliveryAreas->isEmpty(),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::with('parent.parent.parent')->orderBy('name')->get(),
            'currency' => Tenancy::current()->currency,
            'images' => $images,
            'editorImages' => $images->map(fn ($image) => [
                'url' => $image->url(),
                'thumbnail' => $image->thumbnailUrl(),
                'alt' => $image->alt_text ?? '',
            ])->all(),
        ])->title($this->product ? 'Edit '.$this->product->name : 'New product');
    }
}
