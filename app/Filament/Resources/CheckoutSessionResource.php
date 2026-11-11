<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckoutSessionResource\Pages;
use App\Models\CheckoutSession;
use App\Models\Plan;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckoutSessionResource extends Resource
{
    protected static ?string $model = CheckoutSession::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'open' => __('admin/checkout_sessions.status_open'),
            'complete' => __('admin/checkout_sessions.status_complete'),
            'expired' => __('admin/checkout_sessions.status_expired'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/checkout_sessions.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/checkout_sessions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/checkout_sessions.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 45;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'plan', 'coupon']))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('admin/common.owner'))
                    ->description(fn (CheckoutSession $record): ?string => $record->user?->email)
                    ->searchable(['name', 'email']),

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

                TextColumn::make('currency')
                    ->label(__('admin/checkout_sessions.currency'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                TextColumn::make('coupon.code')
                    ->label(__('admin/checkout_sessions.coupon'))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
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
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin/common.status'))
                    ->options(self::statusLabels()),

                SelectFilter::make('plan_id')
                    ->label(__('admin/common.plan'))
                    ->relationship('plan', 'slug', fn (Builder $query): Builder => $query->orderBy('sort'))
                    ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name)
                    ->searchable()
                    ->preload(),

                Filter::make('created_period')
                    ->label(__('admin/checkout_sessions.period'))
                    ->schema([
                        DatePicker::make('from')->label(__('admin/checkout_sessions.from')),
                        DatePicker::make('until')->label(__('admin/checkout_sessions.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $period, string $date): Builder => $period->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $period, string $date): Builder => $period->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                ViewAction::make()->label(__('admin/common.view')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/checkout_sessions.details'))->schema([
                TextEntry::make('user.name')->label(__('admin/common.owner')),
                TextEntry::make('user.email')->label(__('admin/common.email'))->copyable(),
                TextEntry::make('plan.name')->label(__('admin/common.plan'))->badge(),
                TextEntry::make('billing_period')
                    ->label(__('admin/subscriptions.billing'))
                    ->formatStateUsing(fn (string $state): string => __('admin/subscriptions.'.$state)),
                TextEntry::make('amount_total')
                    ->label(__('admin/checkout_sessions.amount'))
                    ->money(fn (CheckoutSession $record): string => $record->currency, divideBy: 100),
                TextEntry::make('currency')
                    ->label(__('admin/checkout_sessions.currency'))
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextEntry::make('coupon.code')->label(__('admin/checkout_sessions.coupon'))->placeholder('—'),
                TextEntry::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state),
                TextEntry::make('created_at')->label(__('admin/checkout_sessions.created_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('completed_at')->label(__('admin/checkout_sessions.completed_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
            ])->columns(2),

            Section::make(__('admin/checkout_sessions.payment'))->schema([
                TextEntry::make('stripe_session_id')->label(__('admin/checkout_sessions.provider_session'))->copyable(),
                TextEntry::make('stripe_subscription_id')->label(__('admin/checkout_sessions.provider_subscription'))->placeholder('—')->copyable(),
                TextEntry::make('url')
                    ->label(__('admin/checkout_sessions.url'))
                    ->placeholder('—')
                    ->url(fn (CheckoutSession $record): ?string => $record->url, shouldOpenInNewTab: true),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCheckoutSessions::route('/'),
            'view' => Pages\ViewCheckoutSession::route('/{record}'),
        ];
    }
}
