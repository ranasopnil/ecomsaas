<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\StorefrontFooter;
use Closure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The bottom of the shopkeeper's own shop: their address, where else to find
 * them, and the pages a customer expects to read before buying.
 *
 * Everything typed here is plain words. Web addresses are tidied up on the way
 * in — "facebook.com/myshop" becomes a working link — and anything that is not
 * an ordinary web address is refused rather than put on the shop.
 */
#[Layout('layouts.admin')]
#[Title('Footer & pages')]
class FooterSettings extends Component
{
    public string $about = '';

    public string $address = '';

    public string $phone = '';

    public string $contact_email = '';

    public string $opening_hours = '';

    public string $facebook_url = '';

    public string $instagram_url = '';

    public string $youtube_url = '';

    public string $tiktok_url = '';

    public string $x_url = '';

    public string $linkedin_url = '';

    public string $whatsapp_number = '';

    public string $about_us = '';

    public string $privacy_policy = '';

    public string $refund_policy = '';

    public string $shipping_policy = '';

    public string $terms = '';

    public string $copyright = '';

    /** Which page is open for writing. Only one at a time keeps the screen calm. */
    public string $openPage = '';

    public function mount(): void
    {
        $footer = StorefrontFooter::query()->first();

        if ($footer === null) {
            // A shop that has not filled this in yet still has an address on
            // file from signing up. Start from that rather than from nothing.
            $this->contact_email = (string) Tenancy::current()?->email;

            return;
        }

        foreach (array_keys($this->rules()) as $field) {
            $this->{$field} = (string) $footer->{$field};
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'about' => ['nullable', 'string', 'max:400'],
            'address' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'opening_hours' => ['nullable', 'string', 'max:120'],

            'facebook_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'instagram_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'youtube_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'tiktok_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'x_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'linkedin_url' => ['nullable', 'string', 'max:200', $this->webAddress()],
            'whatsapp_number' => ['nullable', 'string', 'max:24', 'regex:/^[0-9 +()-]+$/'],

            'about_us' => ['nullable', 'string', 'max:20000'],
            'privacy_policy' => ['nullable', 'string', 'max:20000'],
            'refund_policy' => ['nullable', 'string', 'max:20000'],
            'shipping_policy' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:20000'],

            'copyright' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'contact_email' => 'contact email',
            'opening_hours' => 'opening hours',
            'facebook_url' => 'Facebook link',
            'instagram_url' => 'Instagram link',
            'youtube_url' => 'YouTube link',
            'tiktok_url' => 'TikTok link',
            'x_url' => 'X link',
            'linkedin_url' => 'LinkedIn link',
            'whatsapp_number' => 'WhatsApp number',
            'about_us' => 'About us page',
            'privacy_policy' => 'Privacy policy',
            'refund_policy' => 'Refund policy',
            'shipping_policy' => 'Delivery page',
            'terms' => 'Terms and conditions',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'A WhatsApp number can only have digits, spaces and a + at the front.',
        ];
    }

    public function writePage(string $slug): void
    {
        $this->openPage = $this->openPage === $slug ? '' : $slug;
    }

    public function save(): void
    {
        $this->validate();

        $footer = StorefrontFooter::query()->first() ?? new StorefrontFooter(['tenant_id' => Tenancy::id()]);

        $typed = [];

        foreach (array_keys($this->rules()) as $field) {
            $value = trim($this->{$field});
            $typed[$field] = $value === '' ? null : $value;
        }

        // Links are stored the way they will be used, so the shop front never
        // has to guess what somebody meant by "instagram.com/us".
        foreach (StorefrontFooter::SOCIALS as $social) {
            $typed[$social['column']] = StorefrontFooter::tidyUrl($typed[$social['column']]);
        }

        $footer->fill($typed)->save();

        // Show them back exactly what was stored.
        foreach (array_keys($this->rules()) as $field) {
            $this->{$field} = (string) $footer->{$field};
        }

        $this->dispatch('toast', ['text' => 'Your footer was saved.', 'tone' => 'ok']);
    }

    /**
     * An ordinary web address, however the shopkeeper typed it.
     */
    protected function webAddress(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (trim((string) $value) !== '' && StorefrontFooter::tidyUrl((string) $value) === null) {
                $fail('That does not look like a web address. Paste the link from your browser.');
            }
        };
    }

    public function render()
    {
        return view('livewire.admin.footer-settings');
    }
}
