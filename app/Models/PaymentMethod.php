<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop's own account with one gateway, and whether it is switched on.
 *
 * Secrets go in 'credentials', which is encrypted as a whole and hidden from
 * every array and view. They are shown back only as their last four
 * characters, and changing one is a fresh write, never a read first.
 */
class PaymentMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'gateway', 'display_name', 'is_enabled', 'credentials', 'settings', 'position'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return config("gateways.{$this->gateway}", []);
    }

    public function name(): string
    {
        return $this->display_name ?: ($this->definition()['name'] ?? $this->gateway);
    }

    /**
     * Whether a secret has been entered, without saying what it is.
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

        if ($value === '') {
            return null;
        }

        return str_repeat('•', 8).mb_substr($value, -4);
    }

    /**
     * Replace one secret. Leaving it blank keeps what is stored.
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
     * Every required field has something in it.
     */
    public function isComplete(): bool
    {
        foreach ($this->definition()['fields'] ?? [] as $key => $field) {
            if (($field['type'] ?? 'text') === 'checkbox' || ($field['optional'] ?? false)) {
                continue;
            }

            $value = $field['secret']
                ? ($this->credentials[$key] ?? null)
                : ($this->settings[$key] ?? null);

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    public function isReady(): bool
    {
        return $this->is_enabled && $this->isComplete();
    }
}
