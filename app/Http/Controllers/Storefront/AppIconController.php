<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The shop's own icon, and the note that lets a phone keep it.
 *
 * A shop kept on somebody's home screen needs a square picture and a name.
 * No shopkeeper has uploaded a logo — there is nowhere to upload one yet — so
 * this draws one instead: the shop's first letter on the colour of the look
 * it has chosen. It is the shop's own initial and the shop's own colour, so
 * it is not a stand-in for something truer; it is what the shop is.
 *
 * Drawn on the fly and cached hard: it only changes when the shop is renamed
 * or its look is swapped.
 */
class AppIconController extends Controller
{
    /** How long a browser may keep the icon before asking again. */
    protected const KEEP = 60 * 60 * 24 * 7;

    /**
     * Where a font might be. Nothing here is required — the icon is still
     * drawn without one, just more coarsely.
     */
    protected const FONTS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ];

    public function svg(TemplateCatalogue $templates): Response
    {
        $shop = Tenancy::current();
        [$letter, $accent] = $this->markFor($shop, $templates);

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
                <rect width="512" height="512" rx="112" fill="{$accent}"/>
                <text x="256" y="256" fill="#ffffff" font-size="272" font-weight="700"
                      font-family="system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
                      text-anchor="middle" dominant-baseline="central">{$letter}</text>
            </svg>
            SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age='.self::KEEP,
        ]);
    }

    /**
     * The same mark as a PNG, because an iPhone will not take an SVG for a
     * home screen icon.
     */
    public function png(TemplateCatalogue $templates): Response
    {
        $shop = Tenancy::current();
        [$letter, $accent] = $this->markFor($shop, $templates);

        $size = 180;
        $canvas = imagecreatetruecolor($size, $size);

        [$r, $g, $b] = $this->rgb($accent);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $font = $this->font();

        if ($font !== null) {
            // Measured rather than guessed, so the letter sits in the middle
            // of the square whichever letter it is.
            $points = (int) round($size * 0.55);
            $box = imagettfbbox($points, 0, $font, $letter);

            $width = $box[2] - $box[0];
            $height = $box[1] - $box[7];

            imagettftext(
                $canvas,
                $points,
                0,
                (int) round(($size - $width) / 2 - $box[0]),
                (int) round(($size - $height) / 2 - $box[7]),
                $white,
                $font,
                $letter,
            );
        } else {
            // No font on this machine. The built-in one is a small bitmap, so
            // it is drawn once and blown up — coarse, but centred and still
            // recognisably the shop's letter.
            $stamp = imagecreatetruecolor(20, 20);
            imagefill($stamp, 0, 0, imagecolorallocate($stamp, $r, $g, $b));
            imagestring($stamp, 5, 6, 3, $letter, imagecolorallocate($stamp, 255, 255, 255));
            imagecopyresampled($canvas, $stamp, (int) ($size / 4), (int) ($size / 4), 0, 0, (int) ($size / 2), (int) ($size / 2), 20, 20);
            imagedestroy($stamp);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        imagedestroy($canvas);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.self::KEEP,
        ]);
    }

    /**
     * A font to draw the letter with, or null if this machine has none.
     */
    protected function font(): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        foreach (self::FONTS as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * What a phone reads to decide it may keep this shop on a home screen.
     */
    public function manifest(TemplateCatalogue $templates): JsonResponse
    {
        $shop = Tenancy::current();
        [, $accent] = $this->markFor($shop, $templates);

        return response()->json([
            'name' => $shop->name,
            'short_name' => mb_substr($shop->name, 0, 16),
            'description' => 'Shop at '.$shop->name.'.',
            'start_url' => route('storefront.home'),
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $accent,
            'dir' => 'auto',
            'lang' => str_replace('_', '-', app()->getLocale()),
            'icons' => [
                [
                    'src' => route('storefront.icon'),
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('storefront.icon.png'),
                    'sizes' => '180x180',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, ['Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The shop's first letter, and the colour of the look it is wearing.
     *
     * @return array{0: string, 1: string}
     */
    protected function markFor(Tenant $shop, TemplateCatalogue $templates): array
    {
        $letter = mb_strtoupper(mb_substr(trim($shop->name), 0, 1)) ?: 'S';
        $accent = config('templates.'.$templates->activeFor($shop).'.accent', '#5b3df5');

        return [$letter, $accent];
    }

    /**
     * "#f26e21" as three numbers.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    protected function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '5b3df5';
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
