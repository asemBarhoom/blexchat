<?php

namespace App\Console\Commands;

use App\Mail\ReminderDueMail;
use App\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the owner of every conversation whose reminder has come, once per reminder.
 * Setting a new remind_at clears reminded_at, so the next one is sent too.
 */
#[Signature('conversations:send-reminders')]
#[Description('Email the owners of conversations whose reminder is due')]
class SendDueReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Conversation::query()
            ->with('channel.user')
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminded_at')
            ->chunkById(100, function ($conversations) use (&$sent): void {
                foreach ($conversations as $conversation) {
                    $owner = $conversation->channel?->user;

                    if ($owner !== null) {
                        Mail::to($owner->email)->locale(config('app.locale'))->send(new ReminderDueMail($conversation));
                        $sent++;
                    }

                    $conversation->forceFill(['reminded_at' => now()])->save();
                }
            });

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
