<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelConnectScreenResource\Pages;
use App\Filament\Support\Translations;
use App\Models\ChannelConnectScreen;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ChannelConnectScreenResource extends Resource
{
    /** @var array<string, string> */
    public const METHOD_LABELS = ['meta' => 'عبر Meta', 'tiktok' => 'عبر TikTok'];

    protected static ?string $model = ChannelConnectScreen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'شاشات ربط القنوات';

    protected static ?string $modelLabel = 'شاشة ربط';

    protected static ?string $pluralModelLabel = 'شاشات ربط القنوات';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        $locales = config('app.supported_locales');

        return $schema->components([
            Section::make('القناة')->schema([
                Select::make('platform')->label('المنصة')->required()->unique(ignoreRecord: true)
                    ->options(array_combine(ChannelConnectScreen::PLATFORMS, ['Instagram', 'WhatsApp', 'TikTok', 'Messenger'])),
                Select::make('method')->label('طريقة الربط')->options(self::METHOD_LABELS)->required()->default('meta')
                    ->helperText('تحدد شعار زر الربط.'),
                Translations::input('name', 'الاسم'),
                Translations::input('cta', 'نص زر الربط'),
                Translations::input('lead', 'المقدمة', long: true),
                Translations::input('account_kind', 'نوع الحساب المقبول', required: false),
                Translations::input('can', 'ما يستطيعه Blexchat بعد الربط', required: false),
                Toggle::make('active')->label('ظاهرة')->default(true),
            ])->columns(2),

            Section::make('الخطوات')->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): TagsInput => TagsInput::make("steps.{$locale}")
                        ->label('الخطوات — '.Translations::localeName($locale))
                        ->placeholder('اكتب خطوة واضغط Enter')
                        ->reorderable()
                        ->required(Translations::isFallback($locale)),
                    $locales,
                )),
            ]),

            Section::make('خيارات أخرى ("See more options")')->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): Repeater => Repeater::make("options.{$locale}")
                        ->label('الخيارات — '.Translations::localeName($locale))
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('إضافة خيار')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Translations::field('title', 'العنوان', $locale, required: true),
                            Translations::field('note', 'الملاحظة', $locale, required: false),
                            TextInput::make('url')->label('رابط صفحة المساعدة')->url(),
                        ]),
                    $locales,
                )),
            ])->collapsible(),

            Section::make('أثناء الربط وإعادة الربط')->schema([
                Translations::input('reconnect_cta', 'نص زر إعادة الربط'),
                Translations::input('waiting_in', 'النافذة التي يُنتظر فيها ("Finish in …")'),
            ]),

            Section::make('حساب غير مؤهل')->schema([
                Translations::input('ineligible_title', 'العنوان'),
                Translations::input('ineligible_text', 'النص', long: true),
                Translations::input('ineligible_kind', 'نوع الحساب المرفوض', required: false),
                Translations::input('ineligible_how', 'نص رابط المساعدة', required: false),
                TextInput::make('ineligible_help_url')->label('رابط المساعدة')->url(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('القناة')->searchable(),
                TextColumn::make('platform')->label('المنصة')->badge()->color('gray'),
                TextColumn::make('method')->label('طريقة الربط')->formatStateUsing(fn (string $state): string => self::METHOD_LABELS[$state] ?? $state),
                TextColumn::make('cta')->label('زر الربط'),
                ToggleColumn::make('active')->label('ظاهرة'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف')->modalDescription('ستعيد واجهة الربط لهذه القناة الخطأ 404. لإخفائها فقط، أوقف "ظاهرة".'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannelConnectScreens::route('/'),
            'create' => Pages\CreateChannelConnectScreen::route('/create'),
            'edit' => Pages\EditChannelConnectScreen::route('/{record}/edit'),
        ];
    }
}
