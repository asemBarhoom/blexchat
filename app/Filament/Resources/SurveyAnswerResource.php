<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyAnswerResource\Pages;
use App\Models\SurveyAnswer;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SurveyAnswerResource extends Resource
{
    protected static ?string $model = SurveyAnswer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public static function getNavigationLabel(): string
    {
        return __('admin/survey.answers');
    }

    public static function getModelLabel(): string
    {
        return __('admin/survey.answer_model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/survey.answers');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label(__('admin/common.user'))->relationship('user', 'email')->searchable()->preload()->required(),
            Select::make('survey_question_id')->label(__('admin/common.question'))->required()->live()
                ->relationship('question', 'key')
                ->getOptionLabelFromRecordUsing(fn (SurveyQuestion $record): string => $record->question)
                // One answer per question and owner.
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('user_id', $get('user_id')))
                ->afterStateUpdated(fn (Set $set) => $set('options', [])),
            Select::make('options')->label(__('admin/survey.selected_options'))->multiple()->required()
                ->options(fn (Get $get): array => self::optionLabels($get('survey_question_id')))
                ->maxItems(fn (Get $get): ?int => SurveyQuestion::find($get('survey_question_id'))?->allowsSeveral() === false ? 1 : null),
            TextInput::make('other_text')->label(__('admin/survey.other_text'))->maxLength(255),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'question.options']))
            ->columns([
                TextColumn::make('user.email')->label(__('admin/common.user'))->searchable(),
                TextColumn::make('question.question')->label(__('admin/common.question'))->wrap(),
                TextColumn::make('options')->label(__('admin/survey.answer'))->badge()
                    ->formatStateUsing(fn (string $state, SurveyAnswer $record): string => $record->question->options->firstWhere('key', $state)?->label ?? $state),
                TextColumn::make('other_text')->label(__('admin/survey.other'))->default('—')->wrap(),
                TextColumn::make('updated_at')->label(__('admin/common.updated_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('survey_question_id')->label(__('admin/common.question'))
                    ->relationship('question', 'key')
                    ->getOptionLabelFromRecordUsing(fn (SurveyQuestion $record): string => $record->question),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin/common.view')),
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->label(__('admin/common.delete_selected'))]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function optionLabels(mixed $questionId): array
    {
        return SurveyOption::where('survey_question_id', $questionId)->orderBy('sort')->get()
            ->mapWithKeys(fn (SurveyOption $option): array => [$option->key => $option->label])
            ->all();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSurveyAnswers::route('/')];
    }
}
