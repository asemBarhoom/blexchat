<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($health['checks'] ?? [] as $name => $value)
            <x-filament::section :heading="__('admin/webhooks.' . $name)">
                <x-filament::badge :color="$value === true || is_string($value) ? 'success' : 'danger'">
                    {{ is_string($value) ? $value : ($value ? __('admin/webhooks.configured') : __('admin/webhooks.not_configured')) }}
                </x-filament::badge>
            </x-filament::section>
        @endforeach

        <x-filament::section :heading="__('admin/webhooks.scheduler')">
            <x-filament::badge :color="$this->schedulerReady() ? 'success' : 'danger'">
                {{ $this->schedulerReady() ? __('admin/webhooks.configured') : __('admin/webhooks.not_configured') }}
            </x-filament::badge>
        </x-filament::section>

        <x-filament::section :heading="__('admin/webhooks.channel_driver')">
            <x-filament::badge color="info">{{ $this->driver() === 'fake' ? __('admin/webhooks.fake') : __('admin/webhooks.provider_mode') }}</x-filament::badge>
        </x-filament::section>

        @foreach ($oauth as $provider => $state)
            <x-filament::section :heading="ucfirst($provider)">
                <x-filament::badge :color="($state['configured'] ?? false) ? 'success' : 'danger'">
                    {{ ($state['configured'] ?? false) ? __('admin/webhooks.configured') : __('admin/webhooks.not_configured') }}
                </x-filament::badge>
            </x-filament::section>
        @endforeach

        <x-filament::section class="md:col-span-2" :heading="__('admin/webhooks.last_webhook')">
            <div class="grid gap-3 md:grid-cols-4">
                @foreach ($lastWebhooks as $provider => $time)
                    <div><span class="font-medium">{{ ucfirst($provider) }}</span><br><span class="text-sm text-gray-500">{{ $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans() : __('admin/webhooks.none') }}</span></div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
