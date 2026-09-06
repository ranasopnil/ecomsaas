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

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
