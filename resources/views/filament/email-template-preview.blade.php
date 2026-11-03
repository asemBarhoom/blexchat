{{-- Inline styles: the panel's compiled CSS only has the classes Filament itself uses. --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem;">
    @foreach ($previews as $locale => $preview)
        <div>
            <p style="font-size: 0.875rem; margin-bottom: 0.5rem;" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                <strong>{{ $preview['language'] }}:</strong> {{ $preview['subject'] }}
            </p>
            <iframe srcdoc="{{ $preview['html'] }}" title="{{ $preview['language'] }}" style="width: 100%; height: 34rem; border: 1px solid rgb(0 0 0 / 0.1); border-radius: 0.5rem; background: #fff;"></iframe>
        </div>
    @endforeach
</div>
