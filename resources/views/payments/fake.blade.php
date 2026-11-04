@php
    $money = fn (int $cents): string => '$'.($cents % 100 === 0 ? intdiv($cents, 100) : number_format($cents / 100, 2));
    $annual = $checkout->billing_period === 'annual';
    $renewal = $checkout->plan->priceFor($checkout->billing_period) * 100 * ($annual ? 12 : 1);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('payments.title') }} — Blexchat</title>
    <style>
        :root { color-scheme: light dark; --bg: #f5f5f7; --card: #fff; --text: #1d1d1f; --muted: #6e6e73; --line: #e5e5ea; --accent: #4f46e5; --warn-bg: #fff4e5; --warn: #9a5b00; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111114; --card: #1c1c21; --text: #f5f5f7; --muted: #a1a1aa; --line: #2c2c33; --accent: #818cf8; --warn-bg: #3a2a10; --warn: #fbbf24; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; }
        main { width: 100%; max-width: 420px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        h1 { font-size: 20px; margin: 0; }
        .switch { color: var(--accent); text-decoration: none; font-size: 14px; }
        .badge { display: inline-block; margin: 12px 0 4px; padding: 4px 10px; border-radius: 999px; background: var(--warn-bg); color: var(--warn); font-size: 13px; font-weight: 600; }
        .intro, .renews, .note { color: var(--muted); font-size: 14px; margin: 4px 0 16px; }
        dl { margin: 0; border-top: 1px solid var(--line); }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); }
        dt { color: var(--muted); } dd { margin: 0; text-align: end; }
        .total dt, .total dd { color: var(--text); font-weight: 700; font-size: 17px; }
        .actions { display: grid; gap: 8px; margin-top: 8px; }
        button { width: 100%; padding: 12px; border-radius: 10px; font: inherit; font-weight: 600; cursor: pointer; border: 1px solid var(--line); background: transparent; color: var(--text); }
        button.pay { background: var(--accent); border-color: var(--accent); color: #fff; }
    </style>
</head>
<body>
<main>
    <div class="top">
        <h1>{{ __('payments.title') }}</h1>
        <a class="switch" href="{{ $switchUrl }}">{{ __('payments.switch') }}</a>
    </div>
    <span class="badge">{{ __('payments.badge') }}</span>
    <p class="intro">{{ __('payments.intro') }}</p>

    <dl>
        <div class="row"><dt>{{ __('payments.account') }}</dt><dd>{{ $checkout->user->email }}</dd></div>
        <div class="row"><dt>{{ __('payments.plan') }}</dt><dd>{{ $checkout->plan->name }}</dd></div>
        <div class="row"><dt>{{ __('payments.billing') }}</dt><dd>{{ __($annual ? 'payments.annual' : 'payments.monthly') }}</dd></div>
        @if ($checkout->coupon)
            <div class="row"><dt>{{ __('payments.coupon') }}</dt><dd>{{ __('payments.coupon_off', ['code' => $checkout->coupon->code, 'percent' => $checkout->coupon->percent_off]) }}</dd></div>
        @endif
        <div class="row total"><dt>{{ __('payments.total') }}</dt><dd>{{ $money($checkout->amount_total) }}</dd></div>
    </dl>
    <p class="renews">{{ __('payments.renews', ['amount' => $money($renewal), 'period' => __($annual ? 'payments.year' : 'payments.month')]) }}</p>

    @if ($checkout->isComplete())
        <p class="note">{{ __('payments.already_paid') }}</p>
    @elseif ($checkout->status === 'expired')
        <p class="note">{{ __('payments.expired') }}</p>
    @endif

    <div class="actions">
        @if ($checkout->status !== 'expired')
            <form method="POST" action="{{ $payUrl }}">@csrf<button type="submit" class="pay">{{ __('payments.pay') }}</button></form>
        @endif
        @unless ($checkout->isComplete())
            <form method="POST" action="{{ $declineUrl }}">@csrf<button type="submit">{{ __('payments.decline') }}</button></form>
        @endunless
    </div>
</main>
</body>
</html>
