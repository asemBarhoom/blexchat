<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Checkout — Blexchat</title>
    <style>
        :root { color-scheme: light dark; --bg: #f5f5f7; --text: #1d1d1f; --muted: #6e6e73; --accent: #4f46e5; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111114; --text: #f5f5f7; --muted: #a1a1aa; --accent: #818cf8; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; text-align: center; }
        p { color: var(--muted); margin: 8px 0; } a { color: var(--accent); }
    </style>
    {{-- Always loaded from cdn.paddle.com, as Paddle requires. --}}
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
</head>
<body>
<main>
    <p>{{ $locale === 'ar' ? 'جارٍ فتح صفحة الدفع الآمنة…' : 'Opening the secure checkout…' }}</p>
    <p><a href="{{ $cancelUrl }}">{{ $locale === 'ar' ? 'العودة إلى التطبيق' : 'Back to the app' }}</a></p>
</main>
<script>
    (function () {
        var completed = false;
        @if ($sandbox)
        Paddle.Environment.set('sandbox');
        @endif
        // The checkout for ?_ptxn= opens on its own and takes these settings.
        Paddle.Initialize({
            token: @json($token),
            checkout: { settings: { displayMode: 'overlay', locale: @json($locale), successUrl: @json($successUrl) } },
            eventCallback: function (event) {
                if (event.name === 'checkout.completed') {
                    completed = true;
                }
                if (event.name === 'checkout.closed' && !completed) {
                    window.location.href = @json($cancelUrl);
                }
            }
        });
    })();
</script>
</body>
</html>
