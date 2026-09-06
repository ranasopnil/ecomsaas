<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'parent_id', 'name', 'slug', 'description', 'position', 'is_active', 'demo_batch'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('tenant_id');
    }

    /**
     * "Menswear › Shirts", for showing a category in a list.
     *
     * Walks up to the top on its own if the chain was not loaded, so a page
     * showing a category can never fail because of how it was fetched. The
     * depth is capped in case a category ever ends up inside itself.
     */
    public function path(): string
    {
        $names = [$this->name];
        $ancestor = $this->ancestorOf($this);
        $depth = 0;

        while ($ancestor !== null && $depth < 10) {
            array_unshift($names, $ancestor->name);
            $ancestor = $this->ancestorOf($ancestor);
            $depth++;
        }

        return implode(' › ', $names);
    }

    protected function ancestorOf(self $category): ?self
    {
        if ($category->parent_id === null) {
            return null;
        }

        return $category->relationLoaded('parent')
            ? $category->parent
            : $category->parent()->first();
    }
}
