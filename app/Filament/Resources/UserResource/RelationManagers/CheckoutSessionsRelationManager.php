<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CheckoutSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkoutSessions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/checkout_sessions.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('plan'))
            ->columns([
                TextColumn::make('plan.name')
                    ->label(__('admin/common.plan'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('billing_period')
                    ->label(__('admin/subscriptions.billing'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin/subscriptions.'.$state)),

                TextColumn::make('amount_total')
                    ->label(__('admin/checkout_sessions.amount'))
                    ->money(fn (CheckoutSession $record): string => $record->currency, divideBy: 100),

                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CheckoutSessionResource::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'expired' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin/checkout_sessions.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label(__('admin/checkout_sessions.completed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
