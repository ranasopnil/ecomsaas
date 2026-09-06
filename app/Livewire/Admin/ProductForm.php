<?php

namespace App\Livewire\Admin;

use App\Exceptions\LimitReached;
use App\Facades\Tenancy;
use App\Models\Brand;
use App\Models\Category;
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

    public ?int $brand_id = null;

    /** @var array<int, int> */
    public array $category_ids = [];

    public string $status = Product::STATUS_DRAFT;

    public string $price = '';

    public string $compare_at_price = '';

    public string $sku = '';

    public string $cost_price = '';

    public string $stock = '0';

    public bool $track_inventory = true;

    public bool $allow_backorder = false;

    public string $low_stock_threshold = '';

    /** @var array<int, array{name: string, values: string}> */
    public array $options = [];

    /** @var array<int, array{price: string, cost: string, sku: string, stock: string}> */
    public array $variantRows = [];

    /** Photos waiting to be uploaded. */
    public array $newPhotos = [];

    /** Which combination a photo belongs to, or nothing for the whole product. */
    public ?int $photoForVariant = null;

    public string $message = '';

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

        $variant = $product->defaultVariant();

        if ($variant) {
            $this->price = $variant->price->toDecimal();
            $this->compare_at_price = $variant->compare_at_price_minor !== null
                ? (new Money($variant->compare_at_price_minor, $variant->currency, $variant->currency_exponent))->toDecimal()
                : '';
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
                'price' => $variant->price->toDecimal(),
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
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'sku' => ['nullable', 'string', 'max:100'],
            'stock' => ['required', 'integer'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'options.*.name' => ['nullable', 'string', 'max:60'],
            'options.*.values' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function addOption(): void
    {
        if (count($this->options) >= 3) {
            $this->message = 'Three kinds of choice is the most a product can have.';

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

        $this->assertDecimalsAllowed('price', $this->price);
        $this->assertDecimalsAllowed('compare_at_price', $this->compare_at_price);

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $products = app(ProductService::class);

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'brand_id' => $this->brand_id ?: null,
            'category_ids' => $this->category_ids,
            'status' => $this->status,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price ?: null,
            'cost_price' => $this->cost_price ?: null,
            'sku' => $this->sku ?: null,
            'stock' => (int) $this->stock,
            'track_inventory' => $this->track_inventory,
            'allow_backorder' => $this->allow_backorder,
            'low_stock_threshold' => $this->low_stock_threshold === '' ? null : (int) $this->low_stock_threshold,
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

        $variant->update([
            'price_minor' => Money::fromDecimal($this->price, $currency, $exponent)->minor,
            'compare_at_price_minor' => $this->compare_at_price === ''
                ? null
                : Money::fromDecimal($this->compare_at_price, $currency, $exponent)->minor,
            'cost_price_minor' => $this->cost_price === ''
                ? null
                : Money::fromDecimal($this->cost_price, $currency, $exponent)->minor,
            'sku' => $this->sku ?: null,
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

            if (! is_numeric($row['price'])) {
                $this->addError("variantRows.{$variant->id}.price", 'Enter a price.');

                continue;
            }

            $variant->update([
                'price_minor' => Money::fromDecimal($row['price'], $currency, $exponent)->minor,
                'cost_price_minor' => ($row['cost'] ?? '') === '' || ! is_numeric($row['cost'])
                    ? null
                    : Money::fromDecimal($row['cost'], $currency, $exponent)->minor,
                'sku' => $row['sku'] ?: null,
            ]);

            $level = $inventory->levelFor($variant);

            if ((int) $row['stock'] !== $level->available) {
                $inventory->setTo($variant, (int) $row['stock'], 'Changed on the product page');
            }
        }

        $this->product = $this->product->fresh(['variants.inventory', 'options.values']);
        $this->loadVariantRows();
        $this->message = 'The choices were saved.';
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
        $this->message = 'Photo added.';
    }

    public function deletePhoto(int $imageId): void
    {
        $image = ProductImage::findOrFail($imageId);

        app(ImageService::class)->delete($image);

        $this->product = $this->product->fresh(['images', 'variants.inventory', 'options.values']);
        $this->message = 'Photo removed.';
    }

    public function makePhotoPrimary(int $imageId): void
    {
        app(ImageService::class)->makePrimary(ProductImage::findOrFail($imageId));

        $this->product = $this->product->fresh(['images']);
        $this->message = 'That photo is now the main one.';
    }

    /**
     * Attach a photo to one combination, or to the product as a whole.
     */
    public function assignPhoto(int $imageId, ?string $variantId): void
    {
        $image = ProductImage::findOrFail($imageId);

        $image->update(['product_variant_id' => $variantId === '' || $variantId === null ? null : (int) $variantId]);

        $this->product = $this->product->fresh(['images']);
        $this->message = 'Photo updated.';
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

        return view('livewire.admin.product-form', [
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
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
