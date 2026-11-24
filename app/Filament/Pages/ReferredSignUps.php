<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The accounts that signed up through someone's referral link, and who brought them. A page rather
 * than a resource, so UserResource stays the admin's one resource for users.
 */
class ReferredSignUps extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $slug = 'referrals';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin/referrals.referrals');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/referrals.nav_group');
    }

    public function getTitle(): string
    {
        return __('admin/referrals.referrals');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->whereNotNull('referred_by_user_id')->with(['referrer', 'subscription.plan']))
            ->columns([
                TextColumn::make('email')
                    ->label(__('admin/referrals.customer'))
                    ->description(fn (User $record): ?string => $record->name)
                    ->searchable(['email', 'name']),
                TextColumn::make('referrer.email')
                    ->label(__('admin/referrals.referrer'))
                    ->description(fn (User $record): ?string => $record->referrer?->referral_code),
                TextColumn::make('subscription.plan.name')->label(__('admin/referrals.plan'))->badge()->placeholder('—'),
                TextColumn::make('referred_at')->label(__('admin/referrals.signed_up_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('referred_by_user_id')
                    ->label(__('admin/referrals.referrer'))
                    ->relationship('referrer', 'email')
                    ->searchable(),
            ])
            ->defaultSort('referred_at', 'desc');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
