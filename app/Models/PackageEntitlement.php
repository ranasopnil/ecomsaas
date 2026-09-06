<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one package allows: a ceiling on a counted thing, or a feature switch.
 */
class PackageEntitlement extends Model
{
    use HasFactory;

    protected $fillable = ['package_id', 'feature', 'enabled', 'limit_value'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
