<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaddleGateway;
use App\Services\Payments\PaymentCheckout;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Paddle default payment link: set GET /payments/paddle as "Default payment link" in the Paddle
 * dashboard. Paddle sends the buyer here with `?_ptxn=<transaction id>`, and Paddle.js opens the
 * checkout for it on its own. After paying it goes on to the app's success page; closing it, back to the app.
 */
class PaddleCheckoutController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(config('services.payments.driver') === 'paddle' && filled(config('services.paddle.client_token')), 404);

        $transaction = (string) $request->query('_ptxn', '');
        abort_unless(preg_match('/^txn_[a-z\d]+$/', $transaction) === 1, 404);

        return view('payments.paddle', [
            'token' => config('services.paddle.client_token'),
            'sandbox' => PaddleGateway::isSandbox(),
            'locale' => in_array($request->query('lang'), config('app.supported_locales'), true) ? $request->query('lang') : config('app.locale'),
            'successUrl' => PaymentCheckout::successUrl($transaction),
            'cancelUrl' => PaymentCheckout::cancelUrl(),
        ]);
    }
}
