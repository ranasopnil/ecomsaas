<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One possible answer to an option: "Large", "Red".
 */
class ProductOptionValue extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'product_option_id', 'value', 'position'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_option_value_variant')
            ->withPivot('tenant_id');
    }
}
