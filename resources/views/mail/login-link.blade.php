<x-mail::message>
{{ $line }}

<x-mail::button :url="$url">
{{ $button }}
</x-mail::button>

{{ __('auth.link.expiry', ['minutes' => \App\Models\LoginLink::LIFETIME_MINUTES]) }}

{{ __('auth.link.ignore') }}
</x-mail::message>
