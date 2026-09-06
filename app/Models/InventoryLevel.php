<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of one variant a shop has.
 *
 * Never change 'available' by reading it and writing it back. Use the
 * inventory service, which changes it in one statement in the database.
 */
class InventoryLevel extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'available', 'reserved',
        'track_inventory', 'allow_backorder', 'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'integer',
            'reserved' => 'integer',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'low_stock_threshold' => 'integer',
        ];
    }

    /**
     * Stock that still belongs to something the shop sells and counts.
     *
     * A combination that was withdrawn leaves its history behind but must not
     * keep being counted as sold out.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('track_inventory', true)->whereHas('variant');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isLow(): bool
    {
        return $this->low_stock_threshold !== null
            && $this->track_inventory
            && $this->available <= $this->low_stock_threshold;
    }
}
