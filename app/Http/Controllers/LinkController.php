<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\RedirectResponse;

/**
 * Where a DM's button points: counts the click on the automation's page, then sends the
 * customer on to the link the owner set.
 */
class LinkController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $message = Message::where('link_token', $token)->whereNotNull('button_url')->firstOrFail();

        $message->recordClick();

        return redirect()->away($message->button_url);
    }
}
