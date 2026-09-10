<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one plan says in one row of the table.
 *
 * "yes" draws a tick, "no" or nothing draws a dash, anything else is shown
 * as it was written — "5%", "Unlimited", "Single".
 */
class PlanFeatureValue extends Model
{
    protected $fillable = ['plan_feature_id', 'package_id', 'value'];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class, 'plan_feature_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
