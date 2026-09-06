<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The thing that is actually sold and counted: one size and colour of a
 * product, or the product itself when it has no choices.
 */
class ProductVariant extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'name', 'sku', 'barcode',
        'price_minor', 'currency', 'currency_exponent',
        'compare_at_price_minor', 'cost_price_minor',
        'weight_grams', 'position', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency,currency_exponent',
            'compareAtPrice' => MoneyCast::class.':compare_at_price_minor,currency,currency_exponent',
            'costPrice' => MoneyCast::class.':cost_price_minor,currency,currency_exponent',
            'price_minor' => 'integer',
            'currency_exponent' => 'integer',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryLevel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * What the shopper should see for this combination: its own photo if it
     * has one, otherwise the product's main photo.
     */
    public function displayImage(): ?ProductImage
    {
        $own = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        if ($own->isNotEmpty()) {
            return $own->first();
        }

        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();

        return $product?->primaryImage();
    }

    /**
     * What the shop makes on one of these, before any costs it has not told
     * us about. Null when no cost has been entered.
     */
    public function profitMinor(): ?int
    {
        return $this->cost_price_minor === null ? null : $this->price_minor - $this->cost_price_minor;
    }

    /**
     * Profit as a share of the price, e.g. 42.5 for 42.5%.
     */
    public function marginPercent(): ?float
    {
        if ($this->cost_price_minor === null || $this->price_minor <= 0) {
            return null;
        }

        return round(($this->price_minor - $this->cost_price_minor) / $this->price_minor * 100, 1);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_option_value_variant')
            ->withPivot('tenant_id');
    }

    /**
     * "Large / Red", built from the choices this variant stands for.
     */
    public function choiceLabel(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $values = $this->relationLoaded('optionValues') ? $this->optionValues : $this->optionValues()->get();

        return $values->pluck('value')->implode(' / ') ?: 'Default';
    }
}
