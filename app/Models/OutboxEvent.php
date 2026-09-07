<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Something that must happen because money moved.
 *
 * Written in the same transaction as the payment it belongs to, so it cannot
 * be lost if the queue is. A worker picks these up afterwards.
 */
class OutboxEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'payload', 'attempts', 'last_error', 'available_at', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }
}
