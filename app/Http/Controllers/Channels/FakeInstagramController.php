<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Services\Instagram\FakeInstagramClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The fake Instagram sign-in page (INSTAGRAM_DRIVER=fake): what Instagram's consent screen does,
 * with a choice of answer so every W06 outcome can be tried. It sends the owner back to the
 * frontend with `code` and `state`, or `error=access_denied`, exactly as Instagram would.
 * Signed links only; 404 wherever the fake client is refused.
 */
class FakeInstagramController extends Controller
{
    public function show(Request $request): View
    {
        $userId = $this->ownerFromState($request);
        $locale = in_array($request->query('lang'), config('app.supported_locales'), true) ? $request->query('lang') : config('app.locale');
        App::setLocale($locale);

        return view('channels.fake-instagram', [
            'locale' => $locale,
            'handle' => 'test_shop_'.$userId,
            'answerUrl' => URL::temporarySignedRoute('channels.fake-instagram.answer', now()->addMinutes(15), ['state' => $request->query('state')]),
            'switchUrl' => $request->fullUrlWithQuery(['lang' => $locale === 'ar' ? 'en' : 'ar']),
        ]);
    }

    public function answer(Request $request): RedirectResponse
    {
        $userId = $this->ownerFromState($request);
        $answer = $request->validate(['answer' => ['required', Rule::in([...FakeInstagramClient::KINDS, 'deny'])]])['answer'];
        $state = (string) $request->query('state');

        $query = $answer === 'deny'
            ? ['error' => 'access_denied', 'error_reason' => 'user_denied', 'state' => $state]
            : ['code' => FakeInstagramClient::code($answer, $userId), 'state' => $state];

        return redirect()->away(FakeInstagramClient::returnUrl().'?'.http_build_query($query));
    }

    private function ownerFromState(Request $request): int
    {
        abort_unless(FakeInstagramClient::active(), 404);
        abort_unless($request->hasValidSignatureWhileIgnoring(['lang']), 403);

        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(403);
        }

        return (int) ($state['user'] ?? 0);
    }
}
