<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return ['active' => __('admin/common.enabled'), 'trialing' => __('admin/subscriptions.trialing'), 'trial_ended' => __('admin/subscriptions.trial_ended')];
    }

    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    public static function getNavigationLabel(): string
    {
        return __('admin/subscriptions.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/subscriptions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/subscriptions.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label(__('admin/common.user'))->relationship('user', 'email')->searchable()->preload()->required()
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => __('admin/subscriptions.unique')]),
            Select::make('plan_id')->label(__('admin/common.plan'))->required()
                ->relationship('plan', 'slug', fn (Builder $query): Builder => $query->orderBy('sort'))
                ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name),
            Select::make('billing_period')->label(__('admin/subscriptions.billing'))->options(['monthly' => __('admin/subscriptions.monthly'), 'annual' => __('admin/subscriptions.annual')])->required()->default('monthly'),
            Select::make('status')->label(__('admin/common.status'))->options(array_intersect_key(self::statusLabels(), array_flip(Subscription::STATUSES)))
                ->required()->default('active')->live(),
            DateTimePicker::make('trial_ends_at')->label(__('admin/common.trial_ends_at'))
                ->required(fn (Get $get): bool => $get('status') === 'trialing')
                ->visible(fn (Get $get): bool => $get('status') === 'trialing'),
            DateTimePicker::make('trial_used_at')->label(__('admin/subscriptions.trial_used_at'))
                ->helperText(__('admin/subscriptions.trial_used_help')),
            // Stripe sets these by webhook; editing them here would not change what Stripe charges.
            TextInput::make('stripe_subscription_id')->label(__('admin/subscriptions.stripe_subscription_id'))->disabled()->dehydrated(false)->placeholder(__('admin/subscriptions.no_card'))
                ->helperText(__('admin/subscriptions.stripe_help')),
            DateTimePicker::make('current_period_end')->label(__('admin/subscriptions.current_period_end'))->disabled()->dehydrated(false)
                ->visible(fn (?Subscription $record): bool => $record?->paidByCard() === true),
            TextInput::make('stripe_customer_id')->label(__('admin/subscriptions.stripe_customer_id'))->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?Subscription $record): ?string => $record?->user?->stripe_customer_id)
                ->visibleOn(['edit', 'view']),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'plan']))
            ->columns([
                TextColumn::make('user.email')->label(__('admin/common.user'))->searchable(),
                TextColumn::make('plan.name')->label(__('admin/common.plan'))->badge(),
                TextColumn::make('billing_period')->label(__('admin/subscriptions.billing'))->formatStateUsing(fn (string $state): string => $state === 'annual' ? __('admin/subscriptions.annual') : __('admin/subscriptions.monthly')),
                TextColumn::make('status')->label(__('admin/common.status'))->badge()
                    ->state(fn (Subscription $record): string => $record->trialEnded() ? 'trial_ended' : $record->status)
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('trial_ends_at')->label(__('admin/common.trial_ends_at'))->dateTime('Y-m-d')->placeholder('—')->sortable(),
                IconColumn::make('paid_by_card')->label(__('admin/subscriptions.paid_by_card'))->boolean()
                    ->state(fn (Subscription $record): bool => $record->paidByCard()),
                TextColumn::make('current_period_end')->label(__('admin/subscriptions.renews_at'))->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('stripe_subscription_id')->label(__('admin/subscriptions.stripe_subscription'))->placeholder('—')->copyable()->searchable()
                    ->url(fn (Subscription $record): ?string => $record->paidByCard() ? self::stripeDashboardUrl('subscriptions/'.$record->stripe_subscription_id) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.stripe_customer_id')->label(__('admin/subscriptions.stripe_customer'))->placeholder('—')->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('admin/subscriptions.updated_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('plan_id')->label(__('admin/common.plan'))
                    ->relationship('plan', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name),
                SelectFilter::make('status')->label(__('admin/common.status'))->options(array_intersect_key(self::statusLabels(), array_flip(Subscription::STATUSES))),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin/common.view')),
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete'))->modalDescription(__('admin/subscriptions.delete_note')),
            ]);
    }

    /**
     * A page of the Stripe dashboard, in test mode while the key is a test key.
     */
    public static function stripeDashboardUrl(string $path): string
    {
        $mode = str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? '' : 'test/';

        return "https://dashboard.stripe.com/{$mode}{$path}";
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSubscriptions::route('/')];
    }
}
