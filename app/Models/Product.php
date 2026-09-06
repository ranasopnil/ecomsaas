<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id', 'brand_id', 'name', 'slug', 'description', 'status',
        'has_variants', 'meta_title', 'meta_description', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'has_variants' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('tenant_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('position');
    }

    public function primaryImage(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_primary', true) ?? $images->first();
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    /**
     * On sale in the shop right now.
     */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isOnSale(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * Total stock across every variant of this product.
     */
    public function stockOnHand(): int
    {
        return (int) InventoryLevel::query()
            ->whereIn('product_variant_id', $this->variants->pluck('id'))
            ->sum('available');
    }
}
