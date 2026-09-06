<?php

namespace App\Services\Mail;

use App\Facades\Tenancy;
use App\Mail\TestMail;
use App\Models\MailSetting;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a shop's email through the shop's own server when it has one that
 * works, and through the platform's otherwise.
 *
 * Everything the shop sends to its customers should go through here, so the
 * choice is made in one place.
 */
class TenantMailer
{
    public function __construct(protected MailerBuilder $builder) {}

    /**
     * The shop's own settings, if they are switched on and have been proven.
     */
    public function settings(): ?MailSetting
    {
        if (! Tenancy::check()) {
            return null;
        }

        $settings = MailSetting::query()->first();

        return $settings?->isLive() ? $settings : null;
    }

    /**
     * @param  string|array<int, string>  $to
     */
    public function send(Mailable $mailable, string|array $to): void
    {
        $settings = $this->settings();

        if ($settings === null) {
            Mail::to($to)->send($mailable);

            return;
        }

        $this->builder
            ->build($settings->toMailerConfig(), $settings->from_address, $settings->from_name)
            ->to($to)
            ->send($mailable);
    }

    /**
     * Try the settings for real by sending one email, and remember how it went.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(MailSetting $settings, string $to): array
    {
        try {
            $this->builder
                ->build($settings->toMailerConfig(), $settings->from_address, $settings->from_name)
                ->to($to)
                ->send(new TestMail(Tenancy::current()?->name ?? $settings->from_name));

            $settings->forceFill([
                'status' => MailSetting::STATUS_WORKING,
                'last_tested_at' => now(),
                'last_test_result' => 'A test email was sent to '.$to.'.',
            ])->save();

            return ['ok' => true, 'message' => 'Sent. Check the inbox for '.$to.'.'];
        } catch (Throwable $e) {
            $message = $this->explain($e);

            $settings->forceFill([
                'status' => MailSetting::STATUS_FAILING,
                'last_tested_at' => now(),
                'last_test_result' => $message,
            ])->save();

            // The reason, never the settings, and never the password.
            Log::info('Shop email test failed', ['tenant_id' => $settings->tenant_id, 'reason' => $e->getMessage()]);

            return ['ok' => false, 'message' => $message];
        }
    }

    /**
     * Turn what the mail server said into something a shopkeeper can act on.
     */
    protected function explain(Throwable $e): string
    {
        $raw = $e->getMessage();
        $lower = strtolower($raw);

        return match (true) {
            str_contains($lower, '535') || str_contains($lower, 'authentication') || str_contains($lower, 'auth') => 'The server refused the username or password. Check both, and whether your provider needs an app password instead of your normal one.',
            str_contains($lower, 'connection refused') || str_contains($lower, 'could not connect') || str_contains($lower, 'connection timed out') || str_contains($lower, 'timed out') => 'Could not reach the server. Check the host name and port, and that your provider allows sending from other apps.',
            str_contains($lower, 'ssl') || str_contains($lower, 'tls') || str_contains($lower, 'certificate') => 'The server did not accept the security setting. Try TLS on port 587, or SSL on port 465.',
            str_contains($lower, 'getaddrinfo') || str_contains($lower, 'name or service not known') || str_contains($lower, 'nodename') => 'That host name does not exist. Check the spelling of the mail server address.',
            str_contains($lower, '550') || str_contains($lower, 'sender') || str_contains($lower, 'from address') => 'The server would not send from that address. The from address usually has to match the account you sign in with.',
            default => 'The server said: '.mb_substr($raw, 0, 160),
        };
    }
}
