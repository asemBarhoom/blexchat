<?php

namespace App\Filament\Resources\WebhookEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WebhookEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('provider')->label(__('admin/webhooks.provider')),
                TextEntry::make('status')->label(__('admin/common.status')),
                TextEntry::make('error')->label(__('admin/webhooks.reason'))->placeholder('—'),
                TextEntry::make('created_at')->label(__('admin/common.time'))->dateTime(),
                TextEntry::make('payload')->label(__('admin/webhooks.payload'))->formatStateUsing(fn ($state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->prose()->columnSpanFull(),
            ]);
    }
}
