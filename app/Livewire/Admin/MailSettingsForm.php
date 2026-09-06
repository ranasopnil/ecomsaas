<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Models\MailSetting;
use App\Services\Mail\TenantMailer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Email')]
class MailSettingsForm extends Component
{
    public string $host = '';

    public string $port = '587';

    public string $encryption = 'tls';

    public string $username = '';

    /** Only ever holds a new password being typed. Never the stored one. */
    public string $password = '';

    public string $from_address = '';

    public string $from_name = '';

    public bool $is_enabled = false;

    public string $test_to = '';

    public bool $hasPassword = false;

    public function mount(): void
    {
        $settings = MailSetting::query()->first();
        $store = Tenancy::current();

        $this->from_name = $store?->name ?? '';
        $this->test_to = auth()->user()?->email ?? '';

        if ($settings === null) {
            return;
        }

        $this->host = $settings->host;
        $this->port = (string) $settings->port;
        $this->encryption = $settings->encryption;
        $this->username = (string) $settings->username;
        $this->from_address = $settings->from_address;
        $this->from_name = $settings->from_name;
        $this->is_enabled = $settings->is_enabled;
        $this->hasPassword = $settings->hasPassword();
    }

    protected function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/i'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(MailSetting::ENCRYPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'host' => 'mail server',
            'from_address' => 'from address',
            'from_name' => 'from name',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $settings = MailSetting::query()->first() ?? new MailSetting;

        $changedServer = $settings->exists && (
            $settings->host !== $this->host
            || $settings->port !== (int) $this->port
            || $settings->encryption !== $this->encryption
            || (string) $settings->username !== $this->username
            || $this->password !== ''
        );

        $settings->fill([
            'host' => strtolower(trim($this->host)),
            'port' => (int) $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username ?: null,
            'from_address' => strtolower(trim($this->from_address)),
            'from_name' => trim($this->from_name),
            'is_enabled' => $this->is_enabled,
        ]);

        // A new password replaces the old one outright. Leaving it blank
        // keeps whatever is stored; there is no way to read it back.
        if ($this->password !== '') {
            $settings->password = $this->password;
        }

        // Anything that changes how we connect has to be proven again.
        if (! $settings->exists || $changedServer) {
            $settings->status = MailSetting::STATUS_UNTESTED;
            $settings->last_test_result = null;
        }

        $settings->save();

        $this->password = '';
        $this->hasPassword = $settings->hasPassword();

        $this->dispatch('toast', [
            'text' => $changedServer || $settings->wasRecentlyCreated
                ? 'Saved. Send yourself a test email to prove it works.'
                : 'Saved.',
            'tone' => 'ok',
        ]);
    }

    public function sendTest(): void
    {
        $this->validate(['test_to' => ['required', 'email']]);

        $settings = MailSetting::query()->first();

        if ($settings === null) {
            $this->dispatch('toast', ['text' => 'Save your settings first.', 'tone' => 'bad']);

            return;
        }

        $result = app(TenantMailer::class)->test($settings, $this->test_to);

        $this->dispatch('toast', ['text' => $result['message'], 'tone' => $result['ok'] ? 'ok' : 'bad']);
    }

    public function forget(): void
    {
        MailSetting::query()->delete();

        $this->reset(['host', 'port', 'encryption', 'username', 'password', 'from_address', 'is_enabled', 'hasPassword']);
        $this->from_name = Tenancy::current()?->name ?? '';

        $this->dispatch('toast', ['text' => 'Your email settings were removed. Email goes through the platform again.', 'tone' => 'ok']);
    }

    public function render()
    {
        return view('livewire.admin.mail-settings-form', [
            'settings' => MailSetting::query()->first(),
            'platformFrom' => config('mail.from.address'),
        ]);
    }
}
