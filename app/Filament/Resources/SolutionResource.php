<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolutionResource\Pages;
use App\Filament\Support\Translations;
use App\Models\Solution;
use App\Models\SolutionAutomation;
use App\Models\UseCase;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SolutionResource extends Resource
{
    public static function solutionsGroup(): string
    {
        return __('admin/solutions.solution_pages');
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return ['comment' => __('admin/common.comment'), 'dm' => __('admin/common.dm'), 'story' => __('admin/templates.trigger_story')];
    }

    protected static ?string $model = Solution::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    public static function getNavigationLabel(): string
    {
        return __('admin/solutions.solution_pages');
    }

    public static function getModelLabel(): string
    {
        return __('admin/solutions.solution_page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/solutions.solution_pages');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return self::solutionsGroup();
    }

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/common.page'))->schema([
                Select::make('use_case_id')->label(__('admin/solutions.use_case_card'))
                    ->options(fn (): array => UseCase::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (UseCase $useCase): array => [$useCase->id => "{$useCase->name} — /solutions/{$useCase->slug}"])->all())
                    ->unique(ignoreRecord: true)
                    ->live()
                    ->helperText(__('admin/solutions.a_page_linked_to_a_home_page_card_takes')),

                Group::make([
                    TextInput::make('slug')->label(__('admin/solutions.link_solutions'))->required()->alphaDash()->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->notIn(fn (): array => UseCase::pluck('slug')->all())
                        ->validationMessages(['not_in' => __('admin/solutions.this_link_belongs_to_a_use_case_card_lin')]),
                    Translations::input('name', __('admin/common.name')),
                    Select::make('shape')->label(__('admin/solutions.shape'))->options(array_combine(UseCase::SHAPES, UseCase::SHAPES))->required(),
                    Select::make('tone')->label(__('admin/solutions.background_color'))->options(array_combine(Solution::TONES, Solution::TONES))->required(),
                ])->columns(2)->columnSpanFull()
                    ->visible(fn (Get $get): bool => blank($get('use_case_id'))),

                Translations::input('headline', __('admin/solutions.headline')),
                Translations::input('supporting', __('admin/solutions.supporting_text'), long: true),
                Translations::input('closing', __('admin/solutions.closing_line')),
                Toggle::make('active')->label(__('admin/home.shown_on_the_website'))->default(true),
            ])->columns(2),

            Section::make(__('admin/solutions.sample_business'))->description(__('admin/solutions.the_account_the_preview_conversations_ha'))->schema([
                TextInput::make('sample_handle')->label(__('admin/common.username'))->required()->maxLength(50)->prefix('@'),
                self::avatar('sample_avatar'),
                ColorPicker::make('avatar_color')->label(__('admin/common.avatar_color'))->required()->default('#0b0b0f'),
                ColorPicker::make('avatar_text_color')->label(__('admin/common.avatar_text_color'))->required()->default('#ffffff'),
            ])->columns(2),

            Section::make(__('admin/solutions.the_three_automations'))->schema([
                Repeater::make('automations')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(3)
                    ->maxItems(3)
                    ->defaultItems(3)
                    ->addActionLabel(__('admin/solutions.add_automation'))
                    ->itemLabel(fn (array $state): ?string => $state['title']['ar'] ?? $state['title']['en'] ?? null)
                    ->collapsible()
                    ->schema([
                        Translations::input('title', __('admin/common.title')),
                        Translations::input('line', __('admin/common.description'), long: true),
                        Select::make('trigger_source')->label(__('admin/common.trigger'))->options(self::sourceLabels())->required()->default('comment'),
                        TagsInput::make('trigger_words')->label(__('admin/common.keywords'))
                            ->placeholder(__('admin/templates.keywords_placeholder'))
                            ->helperText(__('admin/solutions.empty_every_comment_message_or_reply_if')),
                        Fieldset::make(__('admin/solutions.conversation'))->schema([
                            Textarea::make('customer')->label(__('admin/solutions.customer_message'))->rows(2)->required(),
                            Textarea::make('reply')->label(__('admin/solutions.auto_reply'))->rows(2)->required(),
                            TextInput::make('button')->label(__('admin/common.button_text'))->required()->maxLength(SolutionAutomation::BUTTON_MAX),
                            TextInput::make('link')->label(__('admin/common.link'))->required()->maxLength(255)
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ])->columns(2),
                        Fieldset::make(__('admin/solutions.post_or_story_optional'))->schema([
                            FileUpload::make('post_image')->label(__('admin/posts.image'))->image()->disk('public')->directory(Solution::UPLOADS)->visibility('public')
                                ->helperText(__('admin/solutions.or_write_a_card_text_instead_of_the_imag')),
                            TextInput::make('post_tile_text')->label(__('admin/solutions.card_text')),
                            Select::make('post_tile_tone')->label(__('admin/common.card_tone'))
                                ->options(array_combine(SolutionAutomation::TILE_TONES, SolutionAutomation::TILE_TONES))
                                ->requiredWith('post_tile_text'),
                            TextInput::make('post_likes')->label(__('admin/solutions.likes'))->maxLength(20),
                            Textarea::make('post_caption')->label(__('admin/common.description'))->rows(2)->columnSpanFull(),
                        ])->columns(2),
                        Fieldset::make(__('admin/solutions.another_account_optional'))->schema([
                            TextInput::make('account_handle')->label(__('admin/common.username'))->maxLength(50)->prefix('@')
                                ->helperText(__('admin/solutions.for_an_agency_s_clients_for_example_empt')),
                            self::avatar('account_avatar'),
                            ColorPicker::make('account_color')->label(__('admin/common.avatar_color')),
                            ColorPicker::make('account_text_color')->label(__('admin/common.avatar_text_color')),
                        ])->columns(2),
                    ]),
            ]),

            Section::make(__('admin/solutions.how_it_works'))->schema([
                Repeater::make('steps')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->minItems(1)
                    ->addActionLabel(__('admin/common.add_step'))
                    ->schema([Translations::input('text', __('admin/solutions.step'))]),
            ]),

            Section::make(__('admin/common.faq'))->schema([
                Repeater::make('faqItems')->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderable()
                    ->addActionLabel(__('admin/survey.create_question'))
                    ->itemLabel(fn (array $state): ?string => $state['question']['ar'] ?? $state['question']['en'] ?? null)
                    ->collapsible()
                    ->schema([
                        Translations::input('question', __('admin/common.question')),
                        Translations::input('answer', __('admin/survey.answer'), long: true),
                    ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('useCase')->withCount('automations'))
            ->columns([
                TextColumn::make('page_name')->label(__('admin/common.name'))
                    ->state(fn (Solution $record): ?string => $record->identity()->name),
                TextColumn::make('page_slug')->label(__('admin/common.link'))->badge()->color('gray')
                    ->state(fn (Solution $record): ?string => '/solutions/'.$record->identity()->slug),
                TextColumn::make('headline')->label(__('admin/common.title'))->wrap()->searchable(),
                TextColumn::make('useCase.name')->label(__('admin/solutions.card'))->placeholder(__('admin/solutions.no_card')),
                ToggleColumn::make('active')->label(__('admin/common.visible')),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolutions::route('/'),
            'create' => Pages\CreateSolution::route('/create'),
            'edit' => Pages\EditSolution::route('/{record}/edit'),
        ];
    }

    private static function avatar(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->label(__('admin/solutions.account_photo_optional'))
            ->helperText(__('admin/solutions.without_one_the_account_s_first_letter_i'))
            ->image()
            ->avatar()
            ->disk('public')
            ->directory(Solution::UPLOADS)
            ->visibility('public');
    }
}
