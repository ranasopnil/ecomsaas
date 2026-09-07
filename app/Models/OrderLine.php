<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing on one order, as it was when it was bought.
 *
 * The name, the unit and the price are copied here on purpose. A shopkeeper
 * renaming a product or changing its price must never change what a customer
 * was charged.
 */
class OrderLine extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'order_id', 'product_id', 'product_variant_id',
        'name', 'variant_name', 'unit',
        'unit_price_minor', 'quantity', 'line_total_minor', 'currency', 'currency_exponent',
    ];

    protected function casts(): array
    {
        return [
            'unitPrice' => MoneyCast::class.':unit_price_minor,currency,currency_exponent',
            'lineTotal' => MoneyCast::class.':line_total_minor,currency,currency_exponent',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'quantity' => 'integer',
            'currency_exponent' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** "Basmati Rice — 5 kg" */
    public function title(): string
    {
        return $this->variant_name ? $this->name.' — '.$this->variant_name : $this->name;
    }
}
