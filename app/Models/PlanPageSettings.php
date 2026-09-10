<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The words above the plans table. One row, ever.
 *
 * The promises across the top are the platform's own — staff write them and
 * staff stand behind them. Nothing here is worked out from anything.
 */
class PlanPageSettings extends Model
{
    protected $table = 'plan_page_settings';

    protected $fillable = ['eyebrow', 'heading', 'heading_accent', 'blurb', 'promises'];

    protected function casts(): array
    {
        return ['promises' => 'array'];
    }

    /**
     * The settings, whether or not anybody has saved them yet.
     */
    public static function current(): self
    {
        return static::query()->first() ?? new self([
            'eyebrow' => 'Plans & pricing',
            'heading' => 'Compare plans. Choose what fits',
            'heading_accent' => 'your business.',
            'blurb' => 'All the features you need to start, grow and scale your online store — with simple, transparent pricing.',
            'promises' => [],
        ]);
    }
}
