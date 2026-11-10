<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ManagePricingPage;
use App\Filament\Resources\PricingCompareRowResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\Plan;
use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PricingCompareRowResource extends Resource
{
    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            'has_ai' => __('admin/pricing.from_the_plan_a_tick_if_it_includes_blex'),
            'paid' => __('admin/pricing.from_the_plan_a_tick_for_paid_plans'),
        ];
    }

    protected static ?string $model = PricingCompareRow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    public static function getNavigationLabel(): string
    {
        return __('admin/pricing.comparison_rows');
    }

    public static function getModelLabel(): string
    {
        return __('admin/pricing.row');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/pricing.comparison_table_rows');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return ManagePricingPage::pricingGroup();
    }

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/pricing.row_2'))->schema([
                Select::make('pricing_compare_group_id')->label(__('admin/pricing.group_2'))->required()
                    ->options(fn (): array => PricingCompareGroup::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (PricingCompareGroup $group): array => [$group->id => $group->title])->all()),
                TextInput::make('key')->label(__('admin/common.key'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText(__('admin/pricing.a_fixed_value_the_frontend_uses_like_aut_2')),
                Translations::input('label', __('admin/pricing.feature')),
                Select::make('source')->label(__('admin/pricing.cell_source'))->options(self::sourceLabels())->placeholder(__('admin/pricing.the_cells_below'))->live()
                    ->helperText(__('admin/pricing.pick_a_plan_source_so_the_plans_and_this')),
                HomeContentFields::active(),
            ])->columns(2),

            Section::make(__('admin/pricing.cells_per_plan'))
                ->description(__('admin/pricing.write_a_text_or_leave_it_empty_to_show_a'))
                ->visible(fn (Get $get): bool => blank($get('source')))
                ->schema([
                    Repeater::make('cells')->hiddenLabel()
                        ->relationship()
                        ->default(fn (): array => Plan::query()->orderBy('sort')->pluck('id')
                            ->map(fn (int $planId): array => ['plan_id' => $planId, 'included' => false])->all())
                        ->addActionLabel(__('admin/pricing.add_cell'))
                        ->itemLabel(fn (array $state): ?string => Plan::find($state['plan_id'] ?? null)?->name)
                        ->collapsible()
                        ->columns(3)
                        ->schema([
                            Select::make('plan_id')->label(__('admin/common.plan'))->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->options(fn (): array => Plan::query()->orderBy('sort')->get()
                                    ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])->all()),
                            Toggle::make('included')->label(__('admin/pricing.included_tick'))->inline(false),
                            Translations::input('text', __('admin/common.text'), required: false),
                        ]),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('group')->withCount('cells'))
            ->columns([
                TextColumn::make('label')->label(__('admin/pricing.feature'))->searchable(),
                TextColumn::make('group.title')->label(__('admin/pricing.group_2')),
                TextColumn::make('key')->label(__('admin/common.key'))->badge()->color('gray'),
                TextColumn::make('source')->label(__('admin/contacts.source'))->placeholder(__('admin/pricing.cells'))
                    ->formatStateUsing(fn (string $state): string => self::sourceLabels()[$state] ?? $state),
                TextColumn::make('cells_count')->label(__('admin/pricing.cells'))->numeric(),
                ToggleColumn::make('active')->label(__('admin/common.visible')),
            ])
            ->filters([
                SelectFilter::make('pricing_compare_group_id')->label(__('admin/pricing.group_2'))
                    ->options(fn (): array => PricingCompareGroup::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (PricingCompareGroup $group): array => [$group->id => $group->title])->all()),
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
            'index' => Pages\ListPricingCompareRows::route('/'),
            'create' => Pages\CreatePricingCompareRow::route('/create'),
            'edit' => Pages\EditPricingCompareRow::route('/{record}/edit'),
        ];
    }
}
