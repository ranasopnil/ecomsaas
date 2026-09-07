<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shop front included in one plan. Platform-owned: staff decide which
 * plans get which templates, and it applies to every shop on that plan.
 */
class PackageTemplate extends Model
{
    protected $fillable = ['package_id', 'template'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
