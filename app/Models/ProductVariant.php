<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
