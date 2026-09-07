<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A merchant's store. Everything else in the system hangs off this.
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'email',
        'country_code',
        'currency',
        'currency_exponent',
        'timezone',
        'prices_include_tax',
        'trial_ends_at',
        'template',
        'template_settings',
        'map_provider',
        'delivers_everywhere',
    ];

    protected function casts(): array
    {
        return [
            'currency_exponent' => 'integer',
            'prices_include_tax' => 'boolean',
            'trial_ends_at' => 'datetime',
            'template_settings' => 'array',
            'delivers_everywhere' => 'boolean',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The hostname the storefront should be reached on.
     */
    public function primaryDomain(): ?Domain
    {
        return $this->domains()
            ->withoutGlobalScope(Concerns\TenantScope::class)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }
}
