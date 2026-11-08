<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * Invites someone to the owner's inbox (Settings · Team). The link opens the frontend's join page,
 * which signs them in and accepts with the token. Its texts are edited in the admin panel
 * (EmailTemplate `team.invite`).
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.team-invitation', with: [
            ...$this->texts(),
            'url' => rtrim((string) config('app.frontend_url'), '/').'/team/join?'.http_build_query(['token' => $this->token]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function texts(): array
    {
        $owner = $this->invitation->owner;

        return EmailTemplate::compose('team.invite', $this->locale ?? App::getLocale(), [
            'owner' => $owner->name ?: $owner->email,
            'business' => $owner->business_name ?: ($owner->name ?: $owner->email),
        ]);
    }
}
