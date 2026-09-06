<?php

namespace App\Services\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Turns a shop's settings into a mailer. Small on purpose, so tests can
 * stand in for it without a real server on the other end.
 */
class MailerBuilder
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function build(array $config, string $fromAddress, string $fromName): Mailer
    {
        $mailer = Mail::build($config);

        $mailer->alwaysFrom($fromAddress, $fromName);

        return $mailer;
    }
}
