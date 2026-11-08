<?php

namespace App\Mail;

use App\Models\Conversation;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * Tells the owner a conversation they asked to be reminded about is due ("Remind me" in the inbox).
 * Its texts are edited in the admin panel (EmailTemplate `reminder.due`).
 */
class ReminderDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reminder-due', with: [
            ...$this->texts(),
            'url' => rtrim((string) config('app.frontend_url'), '/').'/app/inbox',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function texts(): array
    {
        return EmailTemplate::compose('reminder.due', $this->locale ?? App::getLocale(), [
            'name' => $this->conversation->contact_name ?: $this->conversation->contact_platform_id,
        ]);
    }
}
