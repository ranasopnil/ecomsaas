<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One coloured section of the plans table — "Selling", "Operations".
 *
 * Platform-wide, like the plans themselves, so no tenant_id.
 */
class PlanFeatureGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon', 'note', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('position')->orderBy('id');
    }
}
