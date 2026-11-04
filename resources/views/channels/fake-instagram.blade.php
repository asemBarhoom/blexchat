<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('channels.fake.title') }} — Blexchat</title>
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
        .intro { color: var(--muted); font-size: 14px; margin: 4px 0 16px; }
        .account { padding: 12px 0; border-block: 1px solid var(--line); margin-bottom: 16px; font-weight: 600; }
        .actions { display: grid; gap: 8px; }
        button { width: 100%; padding: 12px; border-radius: 10px; font: inherit; font-weight: 600; cursor: pointer; border: 1px solid var(--line); background: transparent; color: var(--text); }
        button.allow { background: var(--accent); border-color: var(--accent); color: #fff; }
    </style>
</head>
<body>
<main>
    <div class="top">
        <h1>{{ __('channels.fake.title') }}</h1>
        <a class="switch" href="{{ $switchUrl }}">{{ __('channels.fake.switch') }}</a>
    </div>
    <span class="badge">{{ __('channels.fake.badge') }}</span>
    <p class="intro">{{ __('channels.fake.intro') }}</p>
    <p class="account">{{ '@'.$handle }}</p>

    <form method="POST" action="{{ $answerUrl }}" class="actions">
        @csrf
        <button type="submit" name="answer" value="business" class="allow">{{ __('channels.fake.allow') }}</button>
        <button type="submit" name="answer" value="personal">{{ __('channels.fake.personal') }}</button>
        <button type="submit" name="answer" value="deny">{{ __('channels.fake.deny') }}</button>
    </form>
</main>
</body>
</html>
