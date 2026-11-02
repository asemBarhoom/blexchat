<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyQuestionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\SurveyQuestion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyQuestionResource extends Resource
{
    /** @var array<string, string> */
    public const KIND_LABELS = ['radio' => 'إجابة واحدة', 'multi' => 'عدة إجابات', 'grid' => 'عدة إجابات (شبكة شعارات)'];

    protected static ?string $model = SurveyQuestion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'أسئلة الاستبيان';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'أسئلة الاستبيان';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('السؤال')->schema([
                TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('ثابت يستخدمه الفرونت والإجابات، مثل heard.'),
                Select::make('kind')->label('النوع')->options(self::KIND_LABELS)->required()->default('multi'),
                Translations::input('question', 'السؤال'),
                Translations::input('hint', 'التلميح', required: false),
                TextInput::make('other_option')->label('خيار "غير ذلك"')->alphaDash()
                    ->helperText('معرّف الخيار الذي يُظهر حقلاً نصياً، مثل other. اتركه فارغاً إن لم يوجد.')
                    ->in(fn (Get $get): array => collect($get('options'))->pluck('key')->filter()->values()->all()),
                Toggle::make('active')->label('ظاهر في الاستبيان')->default(true),
            ])->columns(2),

            Section::make('الخيارات')->schema([
                Repeater::make('options')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(2)
                    ->addActionLabel('إضافة خيار')
                    ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? null)
                    ->collapsible()
                    ->schema([
                        TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->distinct()
                            ->helperText('الفرونت يختار الأيقونة بهذا المعرّف، مثل instagram.'),
                        Translations::input('label', 'النص'),
                        Translations::input('note', 'ملاحظة', required: false),
                    ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['options', 'answers']))
            ->columns([
                TextColumn::make('question')->label('السؤال')->searchable()->wrap(),
                TextColumn::make('key')->label('المعرّف')->badge()->color('gray'),
                TextColumn::make('kind')->label('النوع')->formatStateUsing(fn (string $state): string => self::KIND_LABELS[$state] ?? $state),
                TextColumn::make('options_count')->label('الخيارات')->numeric(),
                TextColumn::make('answers_count')->label('الإجابات')->numeric(),
                ToggleColumn::make('active')->label('ظاهر'),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف')
                    ->modalDescription('ستُحذف معه كل إجابات المستخدمين عن هذا السؤال. لإخفائه فقط، أوقف "ظاهر".'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyQuestions::route('/'),
            'create' => Pages\CreateSurveyQuestion::route('/create'),
            'edit' => Pages\EditSurveyQuestion::route('/{record}/edit'),
        ];
    }
}
