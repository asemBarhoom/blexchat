<?php

return [
    'fake_in_production' => 'Test payments are blocked in production',
    'fake_in_production_note' => 'PAYMENT_DRIVER=fake is set on a production server, so paid plans answer "Payment unavailable". Set PAYMENT_DRIVER=stripe, or PAYMENT_ALLOW_FAKE=true on a development server only.',
    'fake_on' => 'Test payments (PAYMENT_DRIVER=fake) are on',
    'fake_on_note' => 'No money is taken: on this server the checkout page is a test page with "Pay (test)" and "Decline" buttons. :prices For real payments, set PAYMENT_DRIVER=stripe.',
    'live' => 'live mode',
    'nav' => 'Payment settings',
    'plans' => 'Plans on the checkout page',
    'plans_help' => 'The plans the customer can switch between on the checkout page, in plan order.',
    'prices_note' => 'Prices come straight from the plans table (yearly = the yearly monthly price × 12), and the coupon comes off the first payment.',
    'saved' => 'Payment settings saved.',
    'stripe_off' => 'Card payments (Stripe) are off',
    'stripe_off_note' => 'Add STRIPE_SECRET and STRIPE_WEBHOOK_SECRET to the environment. Until then, paid plans answer "Payment unavailable" and free trials work as usual. Webhook URL: :webhook',
    'stripe_on' => 'Card payments (Stripe) are on — :mode',
    'stripe_on_note' => ':prices In Stripe, add a webhook to :webhook for these events: checkout.session.completed, checkout.session.expired, invoice.paid and customer.subscription.deleted.',
    'test' => 'test mode',
    'title' => 'Checkout page settings',
    'trial_reminder_days' => 'Free trial reminder day',
    'trial_reminder_help' => 'The trial day we send the reminder email on. It has to come before the shortest trial ends (:days days).',
    'webhook_secret_missing' => 'Heads up: STRIPE_WEBHOOK_SECRET is empty, so Stripe events won\'t be accepted.',
];
