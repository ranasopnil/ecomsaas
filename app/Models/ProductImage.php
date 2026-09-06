<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'product_id', 'product_variant_id', 'disk', 'path',
        'thumbnail_path', 'original_name', 'size_bytes', 'width', 'height',
        'alt_text', 'position', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'position' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk($this->disk)->url($this->thumbnail_path ?? $this->path);
    }
}
