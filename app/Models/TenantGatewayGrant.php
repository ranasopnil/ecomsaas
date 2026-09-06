<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A gateway staff have handed to one particular shop, whatever its country.
 */
class TenantGatewayGrant extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'gateway', 'granted_by', 'note'];

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'granted_by');
    }
}
