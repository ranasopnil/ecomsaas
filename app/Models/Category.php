<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug', 'description', 'position', 'is_active', 'demo_batch',
        'image_disk', 'image_path', 'image_thumbnail_path', 'image_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
            'image_size_bytes' => 'integer',
        ];
    }

    public function hasImage(): bool
    {
        return $this->image_path !== null;
    }

    /**
     * The full-size picture, or null if the shopkeeper has not added one.
     * Templates that show category pictures fall back to the first letter.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk($this->image_disk ?? 'public')->url($this->image_path);
    }

    /**
     * The small one, for the rows of circles a grocery front page shows.
     */
    public function thumbnailUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk($this->image_disk ?? 'public')->url($this->image_thumbnail_path ?? $this->image_path);
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
     * This category and everything under it.
     *
     * A row for "Dairy" should also show what is in "Dairy › Cheese", so
     * every page that narrows by category asks for the family, not the one.
     * The walk down is capped in case a category ever ends up inside itself.
     *
     * @return Collection<int, int>
     */
    public function familyIds(): Collection
    {
        $ids = collect([$this->id]);
        $frontier = [$this->id];
        $depth = 0;

        while ($frontier !== [] && $depth < 5) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = $ids->merge($frontier);
            $depth++;
        }

        return $ids;
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
