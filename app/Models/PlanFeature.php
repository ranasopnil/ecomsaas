<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row of the plans table.
 *
 * A row either names a real feature — in which case what it says is read
 * from what the plan actually allows, and cannot be typed over — or it is
 * only words, and staff write what each plan says in it.
 */
class PlanFeature extends Model
{
    use HasFactory;

    protected $fillable = ['plan_feature_group_id', 'name', 'note', 'feature', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PlanFeatureGroup::class, 'plan_feature_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PlanFeatureValue::class);
    }

    /**
     * Is this row read from what the platform actually enforces?
     */
    public function isEnforced(): bool
    {
        return $this->feature !== null && config('features.'.$this->feature) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(): ?array
    {
        return $this->feature === null ? null : config('features.'.$this->feature);
    }
}
