<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * The fake driver's payment page (PAYMENT_DRIVER=fake): what Stripe Checkout shows, with "Pay (test)"
 * and "Decline" instead of a card form. Every link is signed, so nobody can pay by guessing an id;
 * the page answers 404 wherever the fake driver is refused.
 */
class FakePaymentController extends Controller
{
    public function show(Request $request, string $session): View
    {
        $checkout = $this->session($request, $session);
        $locale = in_array($request->query('lang'), config('app.supported_locales'), true) ? $request->query('lang') : config('app.locale');
        App::setLocale($locale);

        $expires = now()->addHours(FakeGateway::LINK_HOURS);

        return view('payments.fake', [
            'checkout' => $checkout->load(['plan', 'coupon', 'user']),
            'locale' => $locale,
            'payUrl' => URL::temporarySignedRoute('payments.fake.pay', $expires, ['session' => $session]),
            'declineUrl' => URL::temporarySignedRoute('payments.fake.decline', $expires, ['session' => $session]),
            'switchUrl' => $request->fullUrlWithQuery(['lang' => $locale === 'ar' ? 'en' : 'ar']),
        ]);
    }

    /**
     * "Pay (test)": reported exactly as Stripe's checkout.session.completed is, then on to the success page.
     */
    public function pay(Request $request, string $session, PaymentCheckout $payments): RedirectResponse
    {
        $checkout = $this->session($request, $session);

        if ($checkout->status === 'expired') {
            return redirect()->away(PaymentCheckout::cancelUrl());
        }

        $payments->handleSession(FakeGateway::paidSession($checkout));

        return redirect()->away(PaymentCheckout::successUrl($checkout->stripe_session_id));
    }

    /**
     * "Decline": back to the app with nothing charged, as "Back" on Stripe's page.
     */
    public function decline(Request $request, string $session): RedirectResponse
    {
        $this->session($request, $session);

        return redirect()->away(PaymentCheckout::cancelUrl());
    }

    private function session(Request $request, string $session): CheckoutSession
    {
        abort_unless(config('services.payments.driver') === 'fake' && FakeGateway::allowed(), 404);
        abort_unless($request->hasValidSignatureWhileIgnoring(['lang']), 403);

        return CheckoutSession::where('stripe_session_id', $session)->firstOrFail();
    }
}
