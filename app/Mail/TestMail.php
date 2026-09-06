<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email a shopkeeper sends themselves to prove their settings work.
 */
class TestMail extends Mailable
{
    public function __construct(public string $shopName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.$this->shopName);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
