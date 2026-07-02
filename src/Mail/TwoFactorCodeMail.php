<?php

namespace NickDeKruijk\Leap\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('leap::auth.two_factor_email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'leap::mail.two-factor-code',
        );
    }
}
