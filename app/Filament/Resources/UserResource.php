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
    /** @var array<string, string> */
    public const ONBOARDING_STEPS = ['channel' => 'ربط قناة', 'survey' => 'الاستبيان', 'plan' => 'اختيار خطة', 'done' => 'أنهى التسجيل'];

    /** @var array<string, string> */
    public const ROLES = ['owner' => 'مالك', 'agent' => 'عضو فريق'];

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المحتوى';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الحساب')->schema([
                TextInput::make('name')->label('الاسم')->maxLength(255),
                TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('business_name')->label('اسم النشاط التجاري')->maxLength(255),
                DateTimePicker::make('email_verified_at')->label('تاريخ تأكيد البريد')
                    ->helperText('يُملأ تلقائياً عند أول دخول برابط البريد أو Google.'),
            ])->columns(2),

            Section::make('لوحة التحكم')->schema([
                Toggle::make('is_admin')->label('مشرف (يدخل لوحة التحكم)')->live(),
                // Owners sign in without a password; only an admin needs one, for this panel.
                TextInput::make('password')->label('كلمة المرور')->password()->revealable()->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('is_admin'))
                    ->helperText(fn (string $operation): string => $operation === 'edit' ? 'اتركها فارغة للإبقاء على الحالية.' : 'مطلوبة للمشرفين فقط.')
                    ->visible(fn (Get $get): bool => (bool) $get('is_admin')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teamOwner')->withCount(['channels', 'automations', 'teammates']))
            ->columns([
                TextColumn::make('name')->label('الاسم')->default('—')->searchable()->sortable(),
                TextColumn::make('email')->label('البريد الإلكتروني')->searchable()->copyable(),
                TextColumn::make('business_name')->label('النشاط التجاري')->default('—')->searchable()->toggleable(),
                TextColumn::make('role')->label('الدور')->badge()
                    ->state(fn (User $record): string => self::ROLES[$record->isTeammate() ? 'agent' : 'owner'])
                    ->color(fn (User $record): string => $record->isTeammate() ? 'gray' : 'primary')
                    ->description(fn (User $record): ?string => $record->teamOwner?->email),
                TextColumn::make('teammates_count')->label('أعضاء الفريق')->numeric()->sortable()->toggleable(),
                TextColumn::make('channels_count')->label('القنوات')->numeric()->sortable(),
                TextColumn::make('automations_count')->label('الأتمتة')->numeric()->sortable(),
                IconColumn::make('google_id')->label('Google')->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id))->toggleable(),
                IconColumn::make('is_admin')->label('مشرف')->boolean(),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_admin')->label('مشرف'),
                SelectFilter::make('role')->label('الدور')->options(self::ROLES)
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'owner' => $query->whereNull('team_owner_id'),
                        'agent' => $query->whereNotNull('team_owner_id'),
                        default => $query,
                    }),
                TernaryFilter::make('email_verified_at')->label('البريد مؤكَّد')->nullable(),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
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
        return DeleteAction::make()->label('حذف')
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->disabled(fn (User $record): bool => $record->teammates()->exists())
            ->tooltip(fn (User $record): ?string => $record->teammates()->exists() ? 'احذف أعضاء فريقه أولاً.' : null);
    }

    /**
     * Skips the signed-in admin, other admins and owners with a team, and says how many it skipped.
     */
    public static function deleteBulkAction(): BulkAction
    {
        return BulkAction::make('delete')->label('حذف المحدد')
            ->icon('heroicon-o-trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading('حذف المستخدمين المحددين')
            ->modalDescription('لا يُحذف حسابك ولا حسابات المشرفين ولا المالكون الذين لديهم أعضاء فريق.')
            ->action(function (Collection $records): void {
                $deletable = $records->reject(fn (User $user): bool => $user->is(auth()->user()) || $user->is_admin || $user->teammates()->exists());
                $deletable->each->delete();
                $skipped = $records->count() - $deletable->count();

                Notification::make()
                    ->title("حُذف {$deletable->count()} مستخدم")
                    ->body($skipped > 0 ? "تخطّينا {$skipped}: حسابك أو مشرف أو مالك لديه فريق." : null)
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
        return Action::make('withdrawInvitation')->label('إلغاء دعوة')
            ->icon('heroicon-o-envelope')->color('warning')
            ->visible(fn (User $record): bool => $record->teamInvitations()->exists())
            ->schema([
                Select::make('invitation_id')->label('الدعوة')->required()
                    ->options(fn (User $record): array => $record->teamInvitations()->pluck('email', 'id')->all()),
            ])
            ->action(function (User $record, array $data): void {
                $record->teamInvitations()->whereKey($data['invitation_id'])->delete();

                Notification::make()->title('أُلغيت الدعوة')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الحساب')->schema([
                TextEntry::make('name')->label('الاسم')->default('—'),
                TextEntry::make('email')->label('البريد الإلكتروني')->copyable(),
                TextEntry::make('business_name')->label('النشاط التجاري')->default('—'),
                TextEntry::make('email_verified_at')->label('تأكيد البريد')->dateTime('Y-m-d H:i')->placeholder('غير مؤكَّد'),
                IconEntry::make('google_id')->label('مرتبط بـ Google')->boolean()
                    ->state(fn (User $record): bool => filled($record->google_id)),
                IconEntry::make('is_admin')->label('مشرف')->boolean(),
                TextEntry::make('created_at')->label('تاريخ التسجيل')->dateTime('Y-m-d H:i'),
            ])->columns(2),
            Section::make('التسجيل')->schema([
                TextEntry::make('onboarding_step')->label('الخطوة التالية')->badge()
                    ->state(fn (User $record): string => self::ONBOARDING_STEPS[$record->onboardingStep()]),
                TextEntry::make('survey_completed_at')->label('أنهى الاستبيان')->dateTime('Y-m-d H:i')->placeholder('لم ينهِه بعد'),
                TextEntry::make('subscription.plan.name')->label('الخطة')->placeholder('لم يختر خطة'),
                TextEntry::make('subscription.trial_ends_at')->label('نهاية التجربة')->dateTime('Y-m-d')->placeholder('—'),
            ])->columns(2),
            Section::make('الفريق')->schema([
                TextEntry::make('role')->label('الدور')->badge()
                    ->state(fn (User $record): string => self::ROLES[$record->isTeammate() ? 'agent' : 'owner']),
                TextEntry::make('teamOwner.email')->label('صاحب الحساب')->visible(fn (User $record): bool => $record->isTeammate()),
                TextEntry::make('seats')->label('المقاعد المستخدمة')->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(function (User $record): string {
                        $seats = $record->seatsUsage();

                        return $seats['limit'] === null ? "{$seats['used']} (بلا حد)" : "{$seats['used']} من {$seats['limit']}";
                    })
                    ->helperText('المالك وأعضاء الفريق والدعوات المفتوحة.'),
                TextEntry::make('teammates_list')->label('أعضاء الفريق')->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(fn (User $record): array => $record->teammates()->pluck('email')->all())
                    ->listWithLineBreaks()->placeholder('لا أحد'),
                TextEntry::make('invitations_list')->label('الدعوات المفتوحة')->hidden(fn (User $record): bool => $record->isTeammate())
                    ->state(fn (User $record): array => $record->teamInvitations()->where('expires_at', '>', now())->get()
                        ->map(fn (TeamInvitation $invitation): string => "{$invitation->email} — تنتهي {$invitation->expires_at->format('Y-m-d')}")->all())
                    ->listWithLineBreaks()->placeholder('لا توجد'),
            ])->columns(2),
            Section::make('معلومات النشاط لـ Blexchat AI')->schema([
                TextEntry::make('business_info')->hiddenLabel()->placeholder('لم يكتبها بعد')->prose()->columnSpanFull(),
            ])->collapsible(),
            Section::make('النشاط')->schema([
                TextEntry::make('channels_count')->label('القنوات')->state(fn (User $record): int => $record->channels()->count()),
                TextEntry::make('automations_count')->label('الأتمتة')->state(fn (User $record): int => $record->automations()->count()),
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
