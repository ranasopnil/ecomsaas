<?php

namespace App\Models;

use App\Exceptions\ImmutableFinancialRecord;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in the stock history. Written once, never changed.
 */
class InventoryMovement extends Model
{
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    public const REASON_STOCK_TAKE = 'stock_take';

    public const REASON_RECEIVED = 'received';

    public const REASON_SOLD = 'sold';

    public const REASON_RETURNED = 'returned';

    public const REASON_RELEASED = 'released';

    public const REASON_CORRECTION = 'correction';

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'quantity_change',
        'available_after', 'reason', 'reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'available_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ImmutableFinancialRecord('Stock history cannot be edited. Record another movement instead.');
        });

        static::deleting(function (): void {
            throw new ImmutableFinancialRecord('Stock history cannot be deleted.');
        });
    }
}
