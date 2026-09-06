<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A sign that somebody is looking at the shop at this moment.
 *
 * The fingerprint cannot be turned back into a person: it is a one-way mix of
 * the visitor's address, their browser, the shop, today's date and the app
 * key, and none of those ingredients are stored. It changes every midnight,
 * so nobody can be followed from one day to the next.
 *
 * Rows last a day and are then deleted by `visits:tidy`.
 */
class VisitPulse extends Model
{
    use BelongsToTenant, HasFactory;

    /** How recently somebody must have been seen to count as here now. */
    public const ONLINE_MINUTES = 5;

    /** How long a fingerprint is kept before it is thrown away. */
    public const KEEP_HOURS = 24;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'visitor_hash', 'seen_at'];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }
}
