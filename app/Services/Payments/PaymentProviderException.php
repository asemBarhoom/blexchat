<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * The payment provider could not be reached, refused the request, or is not set up (for Paddle: no
 * default payment link). Nothing was charged; the API answers 502 `payment_provider_error`.
 */
class PaymentProviderException extends RuntimeException {}
