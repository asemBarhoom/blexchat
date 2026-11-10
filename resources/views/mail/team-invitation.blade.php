<x-mail::message>
<div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}" style="text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};">

{{ $line }}

<x-mail::button :url="$url">
{{ $button }}
</x-mail::button>

{{ $expiry }}

</div>
</x-mail::message>
