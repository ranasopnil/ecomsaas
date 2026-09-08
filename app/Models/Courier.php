<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A courier one shop hands parcels to.
 *
 * Each shop keeps its own list, in its own words. Nothing is shared between
 * shops: a courier one merchant uses is none of another merchant's business.
 */
class Courier extends Model
{
    use BelongsToTenant;

    /** Typed in by hand: the shop tells the courier itself. */
    public const MANUAL = 'manual';

    /** The platform books the parcel with the courier as it is handed over. */
    public const AUTOMATIC = 'automatic';

    protected $fillable = [
        'tenant_id', 'name', 'driver', 'mode', 'credentials', 'settings',
        'phone', 'tracking_url', 'is_active', 'position',
    ];

    /**
     * The shop's own account details never leave the model.
     */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * What this courier's module needs, or an empty list for one we have no
     * way of talking to.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return config('couriers.drivers.'.$this->driver, []);
    }

    /**
     * Is the platform booking parcels with this one?
     */
    public function isAutomatic(): bool
    {
        return $this->mode === self::AUTOMATIC && $this->driver !== null;
    }

    /**
     * Could it be, if the shopkeeper filled the details in?
     */
    public function canBeAutomatic(): bool
    {
        return $this->driver !== null && $this->definition() !== [];
    }

    /**
     * Whether a detail has been entered, without saying what it is.
     */
    public function hasSecret(string $field): bool
    {
        $value = $this->credentials[$field] ?? null;

        return $value !== null && $value !== '';
    }

    /**
     * "••••••••1234" — enough to recognise a key, never enough to use it.
     */
    public function maskedSecret(string $field): ?string
    {
        $value = (string) ($this->credentials[$field] ?? '');

        return $value === '' ? null : str_repeat('•', 8).mb_substr($value, -4);
    }

    /**
     * Replace one detail. Leaving it blank keeps what is stored.
     */
    public function putSecret(string $field, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $credentials = $this->credentials ?? [];
        $credentials[$field] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Every detail this courier's module needs has something in it.
     */
    public function isComplete(): bool
    {
        foreach ($this->definition()['fields'] ?? [] as $key => $field) {
            if (($field['type'] ?? 'text') === 'checkbox' || ($field['optional'] ?? false)) {
                continue;
            }

            $value = $field['secret'] ? ($this->credentials[$key] ?? null) : ($this->settings[$key] ?? null);

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Ready to book a parcel: switched to automatic and fully filled in.
     */
    public function isReady(): bool
    {
        return $this->isAutomatic() && $this->isComplete();
    }

    public function isTestMode(): bool
    {
        return (bool) ($this->settings['sandbox'] ?? false);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Where this courier's own page shows a parcel, or null when the shop has
     * not given one.
     *
     * Built by putting the consignment number in place of {code}. Nothing the
     * shopkeeper typed is treated as anything but text.
     */
    public function trackingUrlFor(?string $code): ?string
    {
        $template = trim((string) $this->tracking_url);
        $code = trim((string) $code);

        if ($template === '' || $code === '' || ! preg_match('~^https?://~i', $template)) {
            return null;
        }

        return str_contains($template, '{code}')
            ? str_replace('{code}', rawurlencode($code), $template)
            : $template;
    }

    /**
     * The couriers this shop can hand a parcel to today.
     *
     * @return Collection<int, self>
     */
    public static function inUse()
    {
        return static::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get();
    }
}
