<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Staff's decision on whether one gateway is allowed in one country.
 * Platform-owned: it applies to every shop.
 */
class GatewayAvailability extends Model
{
    protected $fillable = ['gateway', 'country_code', 'is_allowed'];

    protected function casts(): array
    {
        return ['is_allowed' => 'boolean'];
    }
}
