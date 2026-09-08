<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An add-on's price in one currency. Priced per market, like a plan: a figure
 * converted at today's exchange rate is not a commercial decision.
 */
class AddonPrice extends Model
{
    protected $fillable = ['addon_id', 'currency', 'currency_exponent', 'price_minor'];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':price_minor,currency,currency_exponent',
            'price_minor' => 'integer',
            'currency_exponent' => 'integer',
        ];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
