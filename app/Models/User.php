<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Someone who works at a shop: the owner, or staff they invited.
 * Platform staff are not users — they are in the admins table.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';

    public const ROLE_STAFF = 'staff';

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role', 'is_active', 'preferences'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    /**
     * A choice this person has made about their own screens.
     */
    public function prefers(string $key, mixed $fallback = null): mixed
    {
        return data_get($this->preferences, $key, $fallback);
    }

    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);

        $this->forceFill(['preferences' => $preferences])->save();
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
