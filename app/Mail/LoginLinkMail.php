<?php

namespace App\Mail;

use App\Models\LoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The emailed sign-in link. Its button opens the frontend's verify page, which posts the token back.
 */
class LoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LoginLink $link, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __("auth.link.{$this->link->mode}.subject"));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.login-link', with: [
            'line' => __("auth.link.{$this->link->mode}.line"),
            'button' => __("auth.link.{$this->link->mode}.button"),
            'url' => $this->link->url($this->token),
        ]);
    }
}
