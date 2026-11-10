<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Support\Translations;
use App\Models\Plan;
use App\Models\Setting;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    public static function signupBillingGroup(): string
    {
        return __('admin/common.nav_signup_billing');
    }

    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {
        return __('admin/common.plans');
    }

    public static function getModelLabel(): string
    {
        return __('admin/plans.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/common.plans');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return self::signupBillingGroup();
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText(__('admin/plans.slug_help'))
                ->disabled(fn (?Plan $record): bool => $record?->slug === Plan::FREE_SLUG),
            Translations::input('name', __('admin/common.name')),
            Translations::input('tagline', __('admin/plans.tagline'), required: false),

            Section::make(__('admin/plans.pricing'))->schema([
                TextInput::make('monthly_price')->label(__('admin/plans.monthly_price'))->numeric()->integer()->minValue(0)->required()
                    ->helperText(__('admin/plans.monthly_price_help')),
                TextInput::make('annual_price')->label(__('admin/plans.annual_price'))->numeric()->integer()->minValue(0)->required(),
                TextInput::make('contacts_limit')->label(__('admin/plans.contacts_limit'))->numeric()->integer()->minValue(1)->required(),
                TextInput::make('live_automations_limit')->label(__('admin/plans.live_automations_limit'))->numeric()->integer()->minValue(1)->helperText(__('admin/plans.no_limit_help')),
                TextInput::make('teammates_limit')->label(__('admin/plans.teammates_limit'))->numeric()->integer()->minValue(1)->required()
                    ->helperText(__('admin/plans.teammates_help')),
                TextInput::make('trial_days')->label(__('admin/plans.trial_days'))->numeric()->integer()->minValue(0)->maxValue(365)->default(0)
                    ->helperText(__('admin/plans.trial_days_help'))
                    // A trial has to outlast the reminder email (إعدادات الدفع).
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $reminderDay = (int) Setting::get(Setting::TRIAL_REMINDER_DAYS);

                        if ((int) $value > 0 && (int) $value <= $reminderDay) {
                            $fail(__('admin/plans.trial_too_short', ['day' => $reminderDay]));
                        }
                    }),
            ])->columns(2),

            Grid::make(count(config('app.supported_locales')))->schema(array_map(
                fn (string $locale): TagsInput => TagsInput::make("features.{$locale}")
                    ->label(__('admin/plans.features_in', ['locale' => Translations::localeName($locale)]))
                    ->placeholder(__('admin/plans.features_placeholder'))
                    ->reorderable(),
                config('app.supported_locales'),
            ))->columnSpanFull(),

            Grid::make(5)->schema([
                Toggle::make('has_ai')->label(__('admin/plans.has_ai')),
                Toggle::make('has_email_capture')->label(__('admin/plans.has_email_capture')),
                Toggle::make('has_assignment')->label(__('admin/plans.has_assignment')),
                Toggle::make('is_recommended')->label(__('admin/plans.is_recommended')),
                Toggle::make('active')->label(__('admin/plans.active'))->default(true),
            ])->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('subscriptions'))
            ->columns([
                TextColumn::make('name')->label(__('admin/common.name'))->searchable(),
                TextColumn::make('slug')->label(__('admin/common.key'))->badge()->color('gray'),
                TextColumn::make('monthly_price')->label(__('admin/subscriptions.monthly'))->money('USD'),
                TextColumn::make('annual_price')->label(__('admin/plans.annual_column'))->money('USD'),
                TextColumn::make('contacts_limit')->label(__('admin/common.contacts'))->numeric(),
                TextColumn::make('teammates_limit')->label(__('admin/plans.seats'))->numeric(),
                IconColumn::make('has_assignment')->label(__('admin/common.assignment'))->boolean(),
                TextColumn::make('trial_days')->label(__('admin/plans.trial_days_column'))->numeric(),
                IconColumn::make('is_recommended')->label(__('admin/plans.recommended'))->boolean(),
                TextColumn::make('subscriptions_count')->label(__('admin/plans.subscribers'))->numeric(),
                ToggleColumn::make('active')->label(__('admin/common.shown')),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                // Free is where expired trials land, and a plan in use keeps its subscribers' history.
                DeleteAction::make()->label(__('admin/common.delete'))
                    ->hidden(fn (Plan $record): bool => $record->slug === Plan::FREE_SLUG || $record->subscriptions_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }
}
