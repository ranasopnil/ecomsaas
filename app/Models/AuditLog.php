<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing platform staff did that reached across shops.
 *
 * Deliberately not scoped to a shop: the whole point of it is to record the
 * few actions that are not. Rows are written once and never changed.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'admin_id', 'admin_name', 'tenant_id', 'tenant_name',
        'action', 'subject_type', 'subject_id', 'note', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Write down something staff did to one shop.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $action,
        ?Tenant $tenant = null,
        ?Model $subject = null,
        string $note = '',
        array $meta = [],
    ): self {
        $admin = auth('admin')->user();

        return self::create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'note' => $note !== '' ? mb_substr($note, 0, 250) : null,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
