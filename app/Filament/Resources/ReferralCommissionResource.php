<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralCommissionResource\Pages;
use App\Models\ReferralCommission;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What referrers earned, payment by payment. The admin pays them outside Blexchat, then marks them paid.
 */
class ReferralCommissionResource extends Resource
{
    protected static ?string $model = ReferralCommission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ReferralCommission::PENDING => __('admin/referrals.status_pending'),
            ReferralCommission::PAID => __('admin/referrals.status_paid'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/referrals.commissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin/referrals.commission_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/referrals.commissions');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/referrals.nav_group');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ReferralCommission::where('status', ReferralCommission::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        $currency = fn (?ReferralCommission $record): string => $record?->currency ?? (string) config('services.stripe.currency');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['referrer', 'referredUser']))
            ->columns([
                TextColumn::make('referrer.email')
                    ->label(__('admin/referrals.referrer'))
                    ->description(fn (ReferralCommission $record): ?string => $record->referrer?->referral_code)
                    ->searchable(),
                TextColumn::make('referredUser.email')
                    ->label(__('admin/referrals.customer'))
                    ->placeholder(__('admin/referrals.deleted_account'))
                    ->searchable(),
                TextColumn::make('payment_cents')->label(__('admin/referrals.payment'))->money($currency, divideBy: 100),
                TextColumn::make('rate')->label(__('admin/referrals.rate'))->suffix('%'),
                TextColumn::make('amount_cents')
                    ->label(__('admin/referrals.amount'))
                    ->money($currency, divideBy: 100)
                    ->summarize(Sum::make()->money((string) config('services.stripe.currency'), divideBy: 100)),
                TextColumn::make('status')
                    ->label(__('admin/common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === ReferralCommission::PAID ? 'success' : 'warning'),
                TextColumn::make('created_at')->label(__('admin/referrals.earned_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('paid_at')->label(__('admin/referrals.paid_at'))->dateTime('Y-m-d H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin/common.status'))->options(self::statusLabels())->default(ReferralCommission::PENDING),
                SelectFilter::make('referrer_id')
                    ->label(__('admin/referrals.referrer'))
                    ->relationship('referrer', 'email')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label(__('admin/referrals.mark_paid'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin/referrals.mark_paid_confirm'))
                    ->visible(fn (ReferralCommission $record): bool => $record->status === ReferralCommission::PENDING)
                    ->action(function (ReferralCommission $record): void {
                        $record->markPaid();
                        Notification::make()->title(__('admin/referrals.marked_paid'))->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('markPaid')
                    ->label(__('admin/referrals.mark_paid'))
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin/referrals.mark_paid_confirm'))
                    ->action(function (Collection $records): void {
                        $records->each(fn (ReferralCommission $record) => $record->markPaid());
                        Notification::make()->title(__('admin/referrals.marked_paid'))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReferralCommissions::route('/')];
    }
}
