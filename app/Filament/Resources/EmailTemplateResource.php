<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Filament\Support\Translations;
use App\Mail\LoginLinkMail;
use App\Mail\ReminderDueMail;
use App\Mail\TeamInvitationMail;
use App\Models\Conversation;
use App\Models\EmailTemplate;
use App\Models\LoginLink;
use App\Models\TeamInvitation;
use App\Models\User;
use Closure;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The emails' texts, per language. Edit only: the templates are fixed in code (EmailTemplate::TEMPLATES)
 * and added by EmailTemplateSeeder. An empty text uses the default from lang/.
 */
class EmailTemplateResource extends Resource
{
    /** @return array<string, string> */
    public static function templateLabels(): array
    {
        return [
            'login_link.signup' => __('admin/emails.login_link_signup'),
            'login_link.signin' => __('admin/emails.login_link_signin'),
            'reminder.due' => __('admin/emails.reminder_due'),
            'team.invite' => __('admin/emails.team_invite'),
        ];
    }

    /** @return array<string, string> */
    private static function fieldLabels(): array
    {
        return [
            'subject' => __('admin/emails.subject'),
            'line' => __('admin/common.text'),
            'button' => __('admin/common.button_text'),
            'expiry' => __('admin/emails.expiry'),
            'ignore' => __('admin/emails.ignore'),
        ];
    }

    private const LONG_FIELDS = ['line', 'expiry', 'ignore'];

    protected static ?string $model = EmailTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationLabel(): string
    {
        return __('admin/emails.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/emails.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/emails.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (?EmailTemplate $record): string => self::templateLabels()[$record?->key] ?? __('admin/emails.email'))
                ->description(__('admin/emails.defaults_help'))
                // Only the texts this email has: the reminder has no expiry or ignore line.
                ->schema(fn (?EmailTemplate $record): array => array_map(self::textInputs(...), array_keys(EmailTemplate::TEMPLATES[$record?->key] ?? self::fieldLabels()))),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label(__('admin/emails.email'))
                    ->formatStateUsing(fn (string $state): string => self::templateLabels()[$state] ?? $state)
                    ->description(fn (EmailTemplate $record): string => $record->key),
                TextColumn::make('subject')->label(__('admin/emails.subject'))->wrap(),
                TextColumn::make('updated_at')->label(__('admin/common.updated_at'))->since(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * The saved email as it is sent, in the language.
     */
    public static function previewHtml(EmailTemplate $record, string $locale): string
    {
        if ($record->key === 'reminder.due') {
            return (new ReminderDueMail(new Conversation(['contact_name' => 'Reem K.'])))->locale($locale)->render();
        }

        if ($record->key === 'team.invite') {
            $invitation = (new TeamInvitation(['email' => 'preview@blexchat.test']))->setRelation('owner', new User(['name' => 'Reem', 'business_name' => 'Reem Abayas']));

            return (new TeamInvitationMail($invitation, 'preview-token'))->locale($locale)->render();
        }

        $link = new LoginLink(['email' => 'preview@blexchat.test', 'mode' => Str::after($record->key, '.')]);

        return (new LoginLinkMail($link, 'preview-token'))->locale($locale)->render();
    }

    /**
     * One input per language for the field, which refuses dropping a placeholder the default uses
     * and placeholders the email does not fill in.
     */
    private static function textInputs(string $field): Grid
    {
        $locales = config('app.supported_locales');

        return Grid::make(count($locales))->schema(array_map(
            fn (string $locale) => Translations::field("{$field}.{$locale}", self::fieldLabels()[$field].' — '.Translations::localeName($locale), $locale, required: false, long: in_array($field, self::LONG_FIELDS, true))
                ->helperText(fn (?EmailTemplate $record): ?string => $record ? self::placeholderHint($record->key, $field) : null)
                ->rules([fn (?EmailTemplate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record, $field): void {
                    if ($record === null || blank($value)) {
                        return;
                    }

                    $used = EmailTemplate::placeholdersIn($value);

                    foreach (array_diff(EmailTemplate::requiredPlaceholders($record->key, $field), $used) as $missing) {
                        $fail(__('admin/emails.variable_missing', ['name' => ':'.$missing]));
                    }

                    foreach (array_diff($used, EmailTemplate::allowedPlaceholders($record->key)) as $unknown) {
                        $fail(__('admin/emails.variable_unknown', ['name' => ':'.$unknown]));
                    }
                }]),
            $locales,
        ))->columnSpanFull();
    }

    private static function placeholderHint(string $key, string $field): ?string
    {
        $required = EmailTemplate::requiredPlaceholders($key, $field);

        if ($required !== []) {
            return __('admin/emails.must_contain', ['names' => implode(__('admin/emails.separator'), array_map(fn (string $name): string => ":{$name}", $required))]);
        }

        $allowed = EmailTemplate::allowedPlaceholders($key);

        return $allowed === [] ? null : __('admin/emails.can_use', ['names' => implode(__('admin/emails.separator'), array_map(fn (string $name): string => ":{$name}", $allowed))]);
    }
}
