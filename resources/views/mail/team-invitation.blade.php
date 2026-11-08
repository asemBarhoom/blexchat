<x-mail::message>
{{ $line }}

<x-mail::button :url="$url">
{{ $button }}
</x-mail::button>

{{ $expiry }}
</x-mail::message>
