<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step an order took, written down once.
 *
 * These rows are never edited. An order's history is what actually happened,
 * in the order it happened, and a mistake is corrected by taking another step
 * rather than by rewriting the last one.
 */
class OrderEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'order_id', 'from_status', 'to_status', 'note',
        'courier_id', 'courier_name', 'tracking_code', 'user_id', 'user_name',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * "Approved", "Handed to Pathao" — the step, said plainly.
     */
    public function title(): string
    {
        if ($this->to_status === Order::STATUS_HANDED_OVER && $this->courier_name) {
            return 'Handed to '.$this->courier_name;
        }

        return Order::labelFor($this->to_status);
    }

    /**
     * Who took the step. Staff who have left still read properly.
     */
    public function byWhom(): string
    {
        return $this->user_name ?: 'the shop';
    }
}
