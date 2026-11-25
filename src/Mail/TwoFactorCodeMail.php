<?php

namespace NickDeKruijk\Leap\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The code of two factor authentication by e-mail. A Markdown mail, so it takes the mail
 * theme of the site (config mail.markdown), like the password reset mail does.
 */
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
            markdown: 'leap::mail.two-factor-code',
        );
    }
}
