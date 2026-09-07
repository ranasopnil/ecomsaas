<?php

namespace App\Services\Catalogue;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saving and removing product photos.
 *
 * Files go through the Storage facade so that moving to cloud storage later
 * is a settings change, not a rewrite. Big photos are shrunk on the way in:
 * a shop should not be serving a 12 megapixel picture to a phone.
 */
class ImageService
{
    public const MAX_EDGE = 1600;

    public const THUMBNAIL_EDGE = 400;

    /** A category picture is only ever shown small, so it is kept smaller. */
    public const CATEGORY_EDGE = 800;

    public function store(Product $product, UploadedFile $file, ?int $variantId = null): ProductImage
    {
        $this->assertWithinStorageAllowance($file->getSize());

        $saved = $this->saveFile('shops/'.Tenancy::id().'/products/'.$product->getKey(), $file, self::MAX_EDGE);

        return ProductImage::create([
            'product_id' => $product->getKey(),
            'product_variant_id' => $variantId,
            'disk' => $saved['disk'],
            'path' => $saved['path'],
            'thumbnail_path' => $saved['thumbnail_path'],
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $saved['size_bytes'],
            'width' => $saved['width'],
            'height' => $saved['height'],
            'position' => (int) ProductImage::where('product_id', $product->getKey())->max('position') + 1,
            'is_primary' => ! ProductImage::where('product_id', $product->getKey())->where('is_primary', true)->exists(),
        ]);
    }

    /**
     * The one picture a category shows.
     *
     * A category has at most one, so putting a new one on replaces the old and
     * deletes the file, rather than quietly filling the shop's storage.
     */
    public function storeForCategory(Category $category, UploadedFile $file): Category
    {
        $this->assertWithinStorageAllowance($file->getSize());

        $saved = $this->saveFile('shops/'.Tenancy::id().'/categories/'.$category->getKey(), $file, self::CATEGORY_EDGE);

        $this->deleteCategoryImage($category, keepRecord: true);

        $category->forceFill([
            'image_disk' => $saved['disk'],
            'image_path' => $saved['path'],
            'image_thumbnail_path' => $saved['thumbnail_path'],
            'image_size_bytes' => $saved['size_bytes'],
        ])->save();

        return $category;
    }

    /**
     * Take a category's picture away, files and all.
     *
     * $keepRecord is for replacing one picture with another: the old files go,
     * but the columns are about to be overwritten anyway.
     */
    public function deleteCategoryImage(Category $category, bool $keepRecord = false): void
    {
        if ($category->image_path === null) {
            return;
        }

        Storage::disk($category->image_disk ?? 'public')
            ->delete(array_filter([$category->image_path, $category->image_thumbnail_path]));

        if ($keepRecord) {
            return;
        }

        $category->forceFill([
            'image_disk' => null,
            'image_path' => null,
            'image_thumbnail_path' => null,
            'image_size_bytes' => null,
        ])->save();
    }

    /**
     * Shrink a picture, write it and its small version, and say where they went.
     *
     * @return array{disk: string, path: string, thumbnail_path: string|null, size_bytes: int, width: int|null, height: int|null}
     */
    protected function saveFile(string $directory, UploadedFile $file, int $mainEdge): array
    {
        $name = Str::random(24);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $path = $directory.'/'.$name.'.'.$extension;
        $thumbnailPath = $directory.'/'.$name.'-small.'.$extension;

        $original = file_get_contents($file->getRealPath());
        [$width, $height] = $this->dimensions($original);

        $main = $this->resized($original, $mainEdge, skipWhenAlreadySmaller: true) ?? $original;
        $thumbnail = $this->resized($original, self::THUMBNAIL_EDGE);

        Storage::disk('public')->put($path, $main);

        if ($thumbnail !== null) {
            Storage::disk('public')->put($thumbnailPath, $thumbnail);
        }

        [$storedWidth, $storedHeight] = $this->dimensions($main) ?: [$width, $height];

        return [
            'disk' => 'public',
            'path' => $path,
            'thumbnail_path' => $thumbnail !== null ? $thumbnailPath : null,
            'size_bytes' => strlen($main) + strlen((string) $thumbnail),
            'width' => $storedWidth,
            'height' => $storedHeight,
        ];
    }

    public function delete(ProductImage $image): void
    {
        $disk = Storage::disk($image->disk);

        $disk->delete(array_filter([$image->path, $image->thumbnail_path]));

        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;

        $image->delete();

        if ($wasPrimary) {
            ProductImage::where('product_id', $productId)
                ->orderBy('position')
                ->first()
                ?->update(['is_primary' => true]);
        }
    }

    public function makePrimary(ProductImage $image): void
    {
        ProductImage::where('product_id', $image->product_id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    /**
     * How much room the shop's photos take, in megabytes.
     */
    public function storageUsedMb(): float
    {
        $bytes = (int) ProductImage::sum('size_bytes') + (int) Category::sum('image_size_bytes');

        return round($bytes / 1048576, 2);
    }

    protected function assertWithinStorageAllowance(int $incomingBytes): void
    {
        $allowanceMb = Entitlements::limit('storage_mb');

        if ($allowanceMb === null) {
            return;
        }

        $usedMb = $this->storageUsedMb() + ($incomingBytes / 1048576);

        if ($usedMb > $allowanceMb) {
            throw new LimitReached(
                'storage_mb',
                $allowanceMb,
                "Your plan allows {$allowanceMb} MB of images and this photo would go over it. "
                .'Remove some photos or upgrade your plan.'
            );
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function dimensions(string $contents): array
    {
        $info = @getimagesizefromstring($contents);

        return $info ? [$info[0], $info[1]] : [null, null];
    }

    /**
     * Shrink so the longest side is at most $edge. Returns null when the
     * picture cannot be read, or — if asked — when it is already small enough
     * and the original bytes are worth keeping.
     */
    protected function resized(string $contents, int $edge, bool $skipWhenAlreadySmaller = false): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;

        // Already small enough for its purpose: keep the original bytes.
        // A thumbnail is always made, however small the picture, so every
        // template has one size it can rely on.
        if ($skipWhenAlreadySmaller && $width <= $edge && $height <= $edge) {
            return null;
        }

        $ratio = min($edge / max($width, 1), $edge / max($height, 1), 1);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Keep see-through backgrounds see-through.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();

        match ($info['mime'] ?? 'image/jpeg') {
            'image/png' => imagepng($canvas, null, 8),
            'image/gif' => imagegif($canvas),
            'image/webp' => imagewebp($canvas, null, 82),
            default => imagejpeg($canvas, null, 82),
        };

        $output = (string) ob_get_clean();

        return $output ?: null;
    }
}
