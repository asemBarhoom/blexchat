<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\LoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * The emailed sign-in link. Its button opens the frontend's verify page, which posts the token back.
 * Its texts are edited in the admin panel (EmailTemplate `login_link.signup` / `login_link.signin`).
 */
class LoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LoginLink $link, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.login-link', with: [
            ...$this->texts(),
            'url' => $this->link->url($this->token),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function texts(): array
    {
        return EmailTemplate::compose("login_link.{$this->link->mode}", $this->locale ?? App::getLocale(), [
            'minutes' => LoginLink::LIFETIME_MINUTES,
        ]);
    }
}
