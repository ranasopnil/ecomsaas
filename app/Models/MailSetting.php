<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a shop's email goes out from.
 *
 * The password is write-only: encrypted when stored, hidden from every array
 * and view, and never logged. Changing it is a fresh write, not a read first.
 */
class MailSetting extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_UNTESTED = 'untested';

    public const STATUS_WORKING = 'working';

    public const STATUS_FAILING = 'failing';

    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    protected $fillable = [
        'tenant_id', 'host', 'port', 'encryption', 'username', 'password',
        'from_address', 'from_name', 'is_enabled', 'status', 'last_tested_at', 'last_test_result',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function hasPassword(): bool
    {
        return $this->getRawOriginal('password') !== null && $this->getRawOriginal('password') !== '';
    }

    /**
     * Ready to carry the shop's email: switched on and proven to work.
     */
    public function isLive(): bool
    {
        return $this->is_enabled && $this->status === self::STATUS_WORKING;
    }

    /**
     * What Laravel needs to build a mailer from these settings.
     *
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption === 'none' ? null : $this->encryption,
            'scheme' => $this->encryption === 'ssl' ? 'smtps' : null,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'timeout' => 10,
        ];
    }
}
