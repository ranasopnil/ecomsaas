<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How many visits a shop had on one day.
 *
 * Counts only. Nothing here says who visited, what they looked at or where
 * they came from, and nothing else about a visit is kept anywhere.
 *
 * The day is the shop's own day, not the server's: a shop in Dhaka rolls over
 * at midnight in Dhaka.
 */
class VisitDay extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'on_day', 'visits', 'visitors'];

    protected function casts(): array
    {
        return [
            'visits' => 'integer',
            'visitors' => 'integer',
        ];
    }
}
