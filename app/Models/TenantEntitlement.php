<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What one store is allowed to do, right now.
 *
 * Rows with source 'package' come from the plan and are rewritten whenever the
 * plan changes. Rows with source 'override' are set by hand by a super admin
 * and survive plan changes.
 */
class TenantEntitlement extends Model
{
    use BelongsToTenant, HasFactory;

    public const SOURCE_PACKAGE = 'package';

    public const SOURCE_OVERRIDE = 'override';

    protected $fillable = ['tenant_id', 'feature', 'enabled', 'limit_value', 'source', 'note'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }
}
