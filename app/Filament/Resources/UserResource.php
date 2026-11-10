<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\TeamInvitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserResource extends Resource
{
    /** @return array<string, string> */
    public static function onboardingSteps(): array
    {
        return ['channel' => __('admin/users.step_channel'), 'survey' => __('admin/users.step_survey'), 'plan' => __('admin/users.step_plan'), 'done' => __('admin/users.step_done')];
    }

    /** @return array<string, string> */
    public static function roles(): array
    {
        return ['owner' => __('admin/users.role_owner'), 'agent' => __('admin/users.role_agent')];
    }

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('admin/users.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/users.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/users.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/common.account'))->schema([
                TextInput::make('name')->label(__('admin/common.name'))->maxLength(255),
                TextInput::make('email')->label(__('admin/common.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('business_name')->label(__('admin/users.business_name'))->maxLength(255),
                DateTimePicker::make('email_verified_at')->label(__('admin/users.email_verified_at'))
                    ->helperText(__('admin/users.email_verified_help')),
            ])->columns(2),

            Section::make(__('admin/users.panel'))->schema([
                Toggle::make('is_admin')->label(__('admin/users.is_admin'))->live(),
                // Owners sign in without a password; only an admin needs one, for this panel.
                TextInput::make('password')->label(__('admin/users.password'))->password()->revealable()->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('is_admin'))
                    ->helperText(fn (string $operation): string => $operation === 'edit' ? __('admin/users.password_keep') : __('admin/users.password_admins_only'))
                    ->visible(fn (Get $get): bool => (bool) $get('is_admin')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teamOwner')->withCount(['channels', 'automations', 'teammates']))
            ->columns([
                TextColumn::make('name')->label(__('admin/common.name'))->default('—')->searchable()->sortable(),
                TextColumn::make('email')->label(__('admin/common.email'))->searchable()->copyable(),
                TextColumn::make('business_name')->label(__('admin/common.business'))->default('—')->searchable()->toggleable(),
                TextColumn::make('role')->label(__('admin/common.role'))->badge()
                    ->state(fn (User $record): string => self::roles()[$record->isTeammate() ? 'agent' : 'owner'])
                    ->color(fn (User $record): string => $record->isTeammate() ? 'gray' : 'primary')
                    ->description(fn (User $record): ?string => $record->teamOwner?->email),
                TextColumn::make('teammates_count')->label(__('admin/common.teammates'))->numeric()->sortable()->toggleable(),
                TextColumn::make('channels_count')->label(__('admin/common.channels'))->numeric()->sortable(),
                TextColumn::make('automations_count')->label(__('admin/common.automations'))->numeric()->sortable(),
                IconColumn::make('google_id')->label('Google')->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id))->toggleable(),
                IconColumn::make('is_admin')->label(__('admin/common.admin'))->boolean(),
                TextColumn::make('created_at')->label(__('admin/common.signed_up_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_admin')->label(__('admin/common.admin')),
                SelectFilter::make('role')->label(__('admin/common.role'))->options(self::roles())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'owner' => $query->whereNull('team_owner_id'),
                        'agent' => $query->whereNotNull('team_owner_id'),
                        default => $query,
                    }),
                TernaryFilter::make('email_verified_at')->label(__('admin/users.email_verified'))->nullable(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin/common.view')),
                EditAction::make()->label(__('admin/common.edit')),
                self::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deleteBulkAction(),
                ]),
            ]);
    }

    /**
     * Deleting an owner deletes their teammates with them (team_owner_id cascades), so an owner
     * with a team cannot be deleted until the team is empty. Nobody deletes their own account here.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->label(__('admin/common.delete'))
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->disabled(fn (User $record): bool => $record->teammates()->exists())
            ->tooltip(fn (User $record): ?string => $record->teammates()->exists() ? __('admin/users.delete_team_first') : null);
    }

    /**
     * Skips the signed-in admin, other admins and owners with a team, and says how many it skipped.
     */
    public static function deleteBulkAction(): BulkAction
    {
        return BulkAction::make('delete')->label(__('admin/common.delete_selected'))
            ->icon('heroicon-o-trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin/users.bulk_delete_heading'))
            ->modalDescription(__('admin/users.bulk_delete_note'))
            ->action(function (Collection $records): void {
                $deletable = $records->reject(fn (User $user): bool => $user->is(auth()->user()) || $user->is_admin || $user->teammates()->exists());
                $deletable->each->delete();
                $skipped = $records->count() - $deletable->count();

                Notification::make()
                    ->title(__('admin/users.bulk_deleted', ['count' => $deletable->count()]))
                    ->body($skipped > 0 ? __('admin/users.bulk_skipped', ['count' => $skipped]) : null)
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Withdraws one of the owner's invitations, which frees its seat.
     */
    public static function withdrawInvitationAction(): Action
    {
        return Action::make('withdrawInvitation')->label(__('admin/users.withdraw_invitation'))
            ->icon('heroicon-o-envelope')->color('warning')
            ->visible(fn (User $record): bool => $record->teamInvitations()->exists())
            ->schema([
                Select::make('invitation_id')->label(__('admin/users.invitation'))->required()
                    ->options(fn (User $record): array => $record->teamInvitations()->pluck('email', 'id')->all()),
            ])
            ->action(function (User $record, array $data): void {
                $record->teamInvitations()->whereKey($data['invitation_id'])->delete();

                Notification::make()->title(__('admin/users.invitation_withdrawn'))->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/common.account'))->schema([
                TextEntry::make('name')->label(__('admin/common.name'))->default('—'),
                TextEntry::make('email')->label(__('admin/common.email'))->copyable(),
                TextEntry::make('business_name')->label(__('admin/common.business'))->default('—'),
                TextEntry::make('email_verified_at')->label(__('admin/users.email_confirmation'))->dateTime('Y-m-d H:i')->placeholder(__('admin/users.not_confirmed')),
                IconEntry::make('google_id')->label(__('admin/users.google'))->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id)),
                IconEntry::make('is_admin')->label(__('admin/common.admin'))->boolean(),
                TextEntry::make('created_at')->label(__('admin/common.signed_up_at'))->dateTime('Y-m-d H:i'),
            ])->columns(2),
            Section::make(__('admin/users.onboarding'))->schema([
                TextEntry::make('onboarding_step')->label(__('admin/users.next_step'))->badge()
                    ->state(fn (User $record): string => self::onboardingSteps()[$record->onboardingStep()]),
                TextEntry::make('survey_completed_at')->label(__('admin/users.survey_completed_at'))->dateTime('Y-m-d H:i')->placeholder(__('admin/users.not_finished')),
                TextEntry::make('subscription.plan.name')->label(__('admin/users.plan'))->placeholder(__('admin/users.no_plan')),
                TextEntry::make('subscription.trial_ends_at')->label(__('admin/users.trial_ends_at'))->dateTime('Y-m-d')->placeholder('—'),
            ])->columns(2),
            Section::make(__('admin/users.team'))->schema([
                TextEntry::make('role')->label(__('admin/common.role'))->badge()
                    ->state(fn (User $record): string => self::roles()[$record->isTeammate() ? 'agent' : 'owner']),
                TextEntry::make('teamOwner.email')->label(__('admin/common.owner'))->visible(fn (User $record): bool => $record->isTeammate()),
                TextEntry::make('seats')->label(__('admin/users.seats_used'))->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(function (User $record): string {
                        $seats = $record->seatsUsage();

                        return $seats['limit'] === null
                        ? __('admin/users.seats_unlimited', ['used' => $seats['used']])
                        : __('admin/users.seats_of', ['used' => $seats['used'], 'limit' => $seats['limit']]);
                    })
                    ->helperText(__('admin/users.seats_help')),
                TextEntry::make('teammates_list')->label(__('admin/common.teammates'))->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(fn (User $record): array => $record->teammates()->pluck('email')->all())
                    ->listWithLineBreaks()->placeholder(__('admin/users.nobody')),
                TextEntry::make('invitations_list')->label(__('admin/users.open_invitations'))->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(fn (User $record): array => $record->teamInvitations()->where('expires_at', '>', now())->get()
                        ->map(fn (TeamInvitation $invitation): string => __('admin/users.invitation_line', ['email' => $invitation->email, 'date' => $invitation->expires_at->format('Y-m-d')]))->all())
                    ->listWithLineBreaks()->placeholder(__('admin/users.none')),
            ])->columns(2),
            Section::make(__('admin/users.business_info'))->schema([
                TextEntry::make('business_info')->hiddenLabel()->placeholder(__('admin/users.not_written'))->prose()->columnSpanFull(),
            ])->collapsible(),
            Section::make(__('admin/users.activity'))->schema([
                TextEntry::make('channels_count')->label(__('admin/common.channels'))->state(fn (User $record): int => $record->channels()->count()),
                TextEntry::make('automations_count')->label(__('admin/common.automations'))->state(fn (User $record): int => $record->automations()->count()),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
