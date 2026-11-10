<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyQuestionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyQuestionResource extends Resource
{
    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return ['radio' => __('admin/survey.kind_radio'), 'multi' => __('admin/survey.kind_multi'), 'grid' => __('admin/survey.kind_grid')];
    }

    protected static ?string $model = SurveyQuestion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationLabel(): string
    {
        return __('admin/survey.questions');
    }

    public static function getModelLabel(): string
    {
        return __('admin/survey.question_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/survey.questions');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/common.question'))->schema([
                TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText(__('admin/survey.key_help')),
                Select::make('kind')->label(__('admin/common.type'))->options(self::kindLabels())->required()->default('multi'),
                Translations::input('question', __('admin/common.question')),
                Translations::input('hint', __('admin/survey.hint'), required: false),
                TextInput::make('other_option')->label(__('admin/survey.other_option'))->alphaDash()
                    ->helperText(__('admin/survey.other_option_help'))
                    ->in(fn (Get $get): array => collect($get('options'))->pluck('key')->filter()->values()->all()),
                Toggle::make('active')->label(__('admin/survey.active'))->default(true),
            ])->columns(2),

            Section::make(__('admin/common.options'))->schema([
                Repeater::make('options')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(2)
                    ->addActionLabel(__('admin/common.add_option'))
                    ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? null)
                    ->collapsible()
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::withIconBox($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::withIconBox($data))
                    ->schema([
                        TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->distinct()
                            ->helperText(__('admin/survey.option_key_help')),
                        Translations::input('label', __('admin/common.text')),
                        Translations::input('note', __('admin/common.note'), required: false),
                        TextInput::make('icon')->label(__('admin/survey.icon'))->maxLength(50)->regex(SurveyOption::ICON_SLUG_PATTERN)
                            ->helperText(__('admin/survey.icon_help')),
                        FileUpload::make('icon_image')->label(__('admin/survey.icon_image'))
                            ->image()
                            ->disk('public')
                            ->directory('survey/icons')
                            ->visibility('public')
                            ->helperText(__('admin/survey.icon_image_help')),
                        Fieldset::make(__('admin/survey.icon_box'))->statePath('icon_box')->columns(3)->schema([
                            TextInput::make('viewBox')->label('viewBox')->placeholder('0 9.58 24 4.84')->regex(SurveyOption::VIEW_BOX_PATTERN)
                                ->required(fn (Get $get): bool => filled($get('width')) || filled($get('height'))),
                            TextInput::make('width')->label(__('admin/survey.width'))->integer()->minValue(1)->maxValue(200)
                                ->required(fn (Get $get): bool => filled($get('viewBox'))),
                            TextInput::make('height')->label(__('admin/survey.height'))->integer()->minValue(1)->maxValue(200)
                                ->required(fn (Get $get): bool => filled($get('viewBox'))),
                        ]),
                    ]),
            ]),
        ])->columns(1);
    }

    /**
     * Drop an empty crop and store the crop's size as numbers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withIconBox(array $data): array
    {
        $box = $data['icon_box'] ?? null;

        $data['icon_box'] = filled($box['viewBox'] ?? null)
            ? ['viewBox' => $box['viewBox'], 'width' => (int) $box['width'], 'height' => (int) $box['height']]
            : null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['options', 'answers']))
            ->columns([
                TextColumn::make('question')->label(__('admin/common.question'))->searchable()->wrap(),
                TextColumn::make('key')->label(__('admin/common.key'))->badge()->color('gray'),
                TextColumn::make('kind')->label(__('admin/common.type'))->formatStateUsing(fn (string $state): string => self::kindLabels()[$state] ?? $state),
                TextColumn::make('options_count')->label(__('admin/common.options'))->numeric(),
                TextColumn::make('answers_count')->label(__('admin/survey.answers_count'))->numeric(),
                ToggleColumn::make('active')->label(__('admin/common.visible')),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete'))
                    ->modalDescription(__('admin/survey.delete_note')),
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
