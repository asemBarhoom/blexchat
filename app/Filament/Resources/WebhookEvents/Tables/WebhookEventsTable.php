<?php

namespace App\Filament\Resources\WebhookEvents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebhookEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('provider')->label(__('admin/webhooks.provider'))->badge()->sortable(),
                TextColumn::make('status')->label(__('admin/common.status'))->badge()->color(fn (string $state): string => $state === 'received' ? 'success' : 'danger')->sortable(),
                TextColumn::make('error')->label(__('admin/webhooks.reason'))->placeholder('—')->limit(60),
                TextColumn::make('created_at')->label(__('admin/common.time'))->dateTime()->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')->label(__('admin/webhooks.provider'))->options(array_combine(['instagram', 'whatsapp', 'messenger', 'stripe'], ['Instagram', 'WhatsApp', 'Messenger', 'Stripe'])),
                SelectFilter::make('status')->label(__('admin/common.status'))->options(array_combine(['received', 'rejected', 'failed'], [__('admin/webhooks.received'), __('admin/webhooks.rejected'), __('admin/webhooks.failed')])),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
