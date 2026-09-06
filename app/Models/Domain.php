<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hostname that points at a store: either the free *.<platform> subdomain
 * we hand out, or a domain the merchant owns and pointed at us.
 */
class Domain extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_SUBDOMAIN = 'subdomain';

    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'hostname',
        'type',
        'is_primary',
        'status',
        'verified_at',
        'last_checked_at',
        'last_check_result',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function setHostnameAttribute(string $value): void
    {
        $this->attributes['hostname'] = strtolower(trim($value));
    }

    /**
     * Look a hostname up before any store is known.
     *
     * This is the one read that legitimately runs without a tenant bound:
     * it is how the tenant gets resolved in the first place.
     */
    public static function findByHostname(string $hostname): ?self
    {
        return static::withoutGlobalScope(TenantScope::class)
            ->with('tenant')
            ->where('hostname', strtolower(trim($hostname)))
            ->first();
    }

    public function isUsable(): bool
    {
        return $this->type === self::TYPE_SUBDOMAIN
            || $this->status === self::STATUS_VERIFIED;
    }
}
