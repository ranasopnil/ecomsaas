<?php

namespace App\Models;

use App\Facades\Tenancy;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The bottom of a shop's pages, as the shopkeeper filled it in.
 *
 * One row per shop. Everything on it is plain text typed on the shop's own
 * settings screen: it is escaped when shown, never run, and never treated as
 * markup. Web addresses are cleaned up here so a shopkeeper can type
 * "facebook.com/mystore" without it turning into a broken link.
 */
class StorefrontFooter extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * The pages a shop can write, in the order they are shown.
     *
     * The key is the address the page is read at, so those addresses are a
     * fixed list rather than anything a merchant chooses.
     *
     * @var array<string, array{column: string, title: string}>
     */
    public const PAGES = [
        'about-us' => ['column' => 'about_us', 'title' => 'About us'],
        'privacy-policy' => ['column' => 'privacy_policy', 'title' => 'Privacy policy'],
        'refund-policy' => ['column' => 'refund_policy', 'title' => 'Refund policy'],
        'delivery' => ['column' => 'shipping_policy', 'title' => 'Delivery'],
        'terms' => ['column' => 'terms', 'title' => 'Terms and conditions'],
    ];

    /**
     * Where a shop can be found elsewhere.
     *
     * @var array<string, array{column: string, label: string}>
     */
    public const SOCIALS = [
        'facebook' => ['column' => 'facebook_url', 'label' => 'Facebook'],
        'instagram' => ['column' => 'instagram_url', 'label' => 'Instagram'],
        'youtube' => ['column' => 'youtube_url', 'label' => 'YouTube'],
        'tiktok' => ['column' => 'tiktok_url', 'label' => 'TikTok'],
        'x' => ['column' => 'x_url', 'label' => 'X'],
        'linkedin' => ['column' => 'linkedin_url', 'label' => 'LinkedIn'],
    ];

    protected $fillable = [
        'tenant_id',
        'about', 'address', 'phone', 'contact_email', 'opening_hours',
        'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url',
        'linkedin_url', 'x_url', 'whatsapp_number',
        'about_us', 'privacy_policy', 'refund_policy', 'shipping_policy', 'terms',
        'copyright',
    ];

    /**
     * This shop's footer, whether or not the shopkeeper has filled it in.
     *
     * A shop that has never opened the screen still gets a footer: the pages
     * simply have nothing in them and are left out.
     */
    public static function forShop(): self
    {
        return static::query()->first() ?? new self(['tenant_id' => Tenancy::id()]);
    }

    /**
     * The links to show, already cleaned up. Anything left blank is left out.
     *
     * @return Collection<int, array{key: string, label: string, url: string}>
     */
    public function socialLinks(): Collection
    {
        $links = collect(self::SOCIALS)
            ->map(fn (array $social, string $key) => [
                'key' => $key,
                'label' => $social['label'],
                'url' => self::tidyUrl($this->{$social['column']}),
            ])
            ->filter(fn (array $link) => $link['url'] !== null)
            ->values();

        if ($this->whatsappUrl() !== null) {
            $links->push(['key' => 'whatsapp', 'label' => 'WhatsApp', 'url' => $this->whatsappUrl()]);
        }

        return $links;
    }

    /**
     * A chat link from the number the shopkeeper typed, however they typed it.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp_number);

        return $digits === null || strlen($digits) < 6 ? null : 'https://wa.me/'.$digits;
    }

    /**
     * The pages this shop has actually written, in a settled order.
     *
     * @return Collection<int, array{slug: string, title: string}>
     */
    public function writtenPages(): Collection
    {
        return collect(self::PAGES)
            ->map(fn (array $page, string $slug) => [
                'slug' => $slug,
                'title' => $page['title'],
                'body' => trim((string) $this->{$page['column']}),
            ])
            ->filter(fn (array $page) => $page['body'] !== '')
            ->map(fn (array $page) => ['slug' => $page['slug'], 'title' => $page['title']])
            ->values();
    }

    /**
     * One page to read, or null when the shop has not written it.
     *
     * @return array{slug: string, title: string, body: string}|null
     */
    public function page(string $slug): ?array
    {
        $page = self::PAGES[$slug] ?? null;

        if ($page === null) {
            return null;
        }

        $body = trim((string) $this->{$page['column']});

        return $body === '' ? null : ['slug' => $slug, 'title' => $page['title'], 'body' => $body];
    }

    /**
     * Has the shopkeeper put anything at all in here?
     */
    public function hasAnything(): bool
    {
        return $this->socialLinks()->isNotEmpty()
            || $this->writtenPages()->isNotEmpty()
            || trim((string) $this->about) !== ''
            || trim((string) $this->address) !== ''
            || trim((string) $this->phone) !== '';
    }

    /**
     * A web address a person typed, made safe to put in a link.
     *
     * Anything that is not plainly http or https comes back as nothing, so a
     * shopkeeper pasting something odd can never put a javascript: link on
     * their own shop.
     */
    public static function tidyUrl(?string $typed): ?string
    {
        $typed = trim((string) $typed);

        if ($typed === '') {
            return null;
        }

        if (! preg_match('~^https?://~i', $typed)) {
            // No scheme typed, and nothing that looks like another one.
            if (str_contains($typed, ':')) {
                return null;
            }

            $typed = 'https://'.ltrim($typed, '/');
        }

        return filter_var($typed, FILTER_VALIDATE_URL) === false ? null : $typed;
    }
}
