<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Real photographs for a demo shop, from Wikimedia Commons.
 *
 * Only freely licensed pictures are used, each one named in
 * database/demo/photo-credits.md with its licence. They exist so the shop
 * fronts can be judged with real food on the shelves; a real shop uploads its
 * own photographs and none of this is involved.
 *
 * Every file is kept after the first fetch, so filling a shop a second time
 * asks Wikimedia for nothing. A picture that cannot be fetched returns null
 * and the caller falls back to a plain painted square: a demo shop must never
 * fail to build because a web site was slow.
 */
class StockPhotos
{
    protected const CACHE = 'demo-photos';

    protected const SOURCE = 'https://commons.wikimedia.org/wiki/Special:FilePath/';

    /** Big enough for a product page, small enough not to punish the server. */
    protected const WIDTH = 1200;

    /**
     * Put the photo on disk and hand back a path to a temporary copy of it.
     *
     * The caller is expected to delete that copy once it has been stored
     * against a product, the same as it would with an uploaded file.
     */
    public function fetch(string $file): ?string
    {
        $bytes = $this->bytes($file);

        if ($bytes === null) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'demo-photo-');

        if ($path === false) {
            return null;
        }

        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * The photo itself, from the cache where possible.
     */
    protected function bytes(string $file): ?string
    {
        $disk = Storage::disk('local');
        $cached = self::CACHE.'/'.md5($file).'.'.$this->extension($file);

        if ($disk->exists($cached)) {
            return $disk->get($cached);
        }

        try {
            $response = Http::withHeaders([
                // Wikimedia asks callers to say who they are.
                'User-Agent' => config('app.name').' demo seeder ('.config('app.url').')',
            ])
                ->timeout(30)
                ->get(self::SOURCE.rawurlencode($file), ['width' => self::WIDTH]);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed() || $response->body() === '') {
            return null;
        }

        $disk->put($cached, $response->body());

        return $response->body();
    }

    protected function extension(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
