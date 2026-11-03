<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Filament\Support\Translations;
use App\Mail\LoginLinkMail;
use App\Models\EmailTemplate;
use App\Models\LoginLink;
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
    /** @var array<string, string> */
    public const TEMPLATE_LABELS = [
        'login_link.signup' => 'رابط الدخول — حساب جديد',
        'login_link.signin' => 'رابط الدخول — تسجيل الدخول',
    ];

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'subject' => 'عنوان الرسالة',
        'line' => 'النص',
        'button' => 'نص الزر',
        'expiry' => 'مدة صلاحية الرابط',
        'ignore' => 'ملاحظة التجاهل',
    ];

    private const LONG_FIELDS = ['line', 'expiry', 'ignore'];

    protected static ?string $model = EmailTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'نصوص الرسائل البريدية';

    protected static ?string $modelLabel = 'رسالة بريدية';

    protected static ?string $pluralModelLabel = 'نصوص الرسائل البريدية';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

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
            Section::make(fn (?EmailTemplate $record): string => self::TEMPLATE_LABELS[$record?->key] ?? 'الرسالة')
                ->description('اترك أي نص فارغاً لاستخدام النص الافتراضي.')
                ->schema(array_map(self::textInputs(...), array_keys(self::FIELD_LABELS))),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('الرسالة')
                    ->formatStateUsing(fn (string $state): string => self::TEMPLATE_LABELS[$state] ?? $state)
                    ->description(fn (EmailTemplate $record): string => $record->key),
                TextColumn::make('subject')->label('عنوان الرسالة')->wrap(),
                TextColumn::make('updated_at')->label('آخر تعديل')->since(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label('تعديل'),
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
            fn (string $locale) => Translations::field("{$field}.{$locale}", self::FIELD_LABELS[$field].' — '.Translations::localeName($locale), $locale, required: false, long: in_array($field, self::LONG_FIELDS, true))
                ->helperText(fn (?EmailTemplate $record): ?string => $record ? self::placeholderHint($record->key, $field) : null)
                ->rules([fn (?EmailTemplate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record, $field): void {
                    if ($record === null || blank($value)) {
                        return;
                    }

                    $used = EmailTemplate::placeholdersIn($value);

                    foreach (array_diff(EmailTemplate::requiredPlaceholders($record->key, $field), $used) as $missing) {
                        $fail("يجب أن يبقى المتغير :{$missing} في النص.");
                    }

                    foreach (array_diff($used, EmailTemplate::allowedPlaceholders($record->key)) as $unknown) {
                        $fail("المتغير :{$unknown} غير معروف في هذه الرسالة.");
                    }
                }]),
            $locales,
        ))->columnSpanFull();
    }

    private static function placeholderHint(string $key, string $field): ?string
    {
        $required = EmailTemplate::requiredPlaceholders($key, $field);

        if ($required !== []) {
            return 'يجب أن يحتوي على: '.implode('، ', array_map(fn (string $name): string => ":{$name}", $required));
        }

        $allowed = EmailTemplate::allowedPlaceholders($key);

        return $allowed === [] ? null : 'يمكن استخدام: '.implode('، ', array_map(fn (string $name): string => ":{$name}", $allowed));
    }
}
