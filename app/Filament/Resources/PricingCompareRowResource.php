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
    /** @var array<string, string> */
    public const SOURCE_LABELS = [
        'has_ai' => 'من الخطة: صح إن كانت تشمل Blexchat AI',
        'paid' => 'من الخطة: صح للخطط المدفوعة',
    ];

    protected static ?string $model = PricingCompareRow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'صفوف المقارنة';

    protected static ?string $modelLabel = 'صف';

    protected static ?string $pluralModelLabel = 'صفوف جدول المقارنة';

    protected static string|\UnitEnum|null $navigationGroup = ManagePricingPage::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الصف')->schema([
                Select::make('pricing_compare_group_id')->label('المجموعة')->required()
                    ->options(fn (): array => PricingCompareGroup::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (PricingCompareGroup $group): array => [$group->id => $group->title])->all()),
                TextInput::make('key')->label('المعرّف')->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('ثابت يستخدمه الفرونت، مثل automations.'),
                Translations::input('label', 'الميزة'),
                Select::make('source')->label('مصدر الخلايا')->options(self::SOURCE_LABELS)->placeholder('الخلايا أدناه')->live()
                    ->helperText('اختر مصدراً من الخطة حتى لا تتكرر معلومة موجودة في الخطط.'),
                HomeContentFields::active(),
            ])->columns(2),

            Section::make('الخلايا لكل خطة')
                ->description('اكتب نصاً، أو اتركه فارغاً لتظهر علامة صح (مشمولة) أو شرطة. {contacts} داخل النص = حد جهات الاتصال للخطة. خطة بلا خلية تظهر لها شرطة.')
                ->visible(fn (Get $get): bool => blank($get('source')))
                ->schema([
                    Repeater::make('cells')->hiddenLabel()
                        ->relationship()
                        ->default(fn (): array => Plan::query()->orderBy('sort')->pluck('id')
                            ->map(fn (int $planId): array => ['plan_id' => $planId, 'included' => false])->all())
                        ->addActionLabel('إضافة خلية')
                        ->itemLabel(fn (array $state): ?string => Plan::find($state['plan_id'] ?? null)?->name)
                        ->collapsible()
                        ->columns(3)
                        ->schema([
                            Select::make('plan_id')->label('الخطة')->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->options(fn (): array => Plan::query()->orderBy('sort')->get()
                                    ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])->all()),
                            Toggle::make('included')->label('مشمولة (صح)')->inline(false),
                            Translations::input('text', 'النص', required: false),
                        ]),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('group')->withCount('cells'))
            ->columns([
                TextColumn::make('label')->label('الميزة')->searchable(),
                TextColumn::make('group.title')->label('المجموعة'),
                TextColumn::make('key')->label('المعرّف')->badge()->color('gray'),
                TextColumn::make('source')->label('المصدر')->placeholder('الخلايا')
                    ->formatStateUsing(fn (string $state): string => self::SOURCE_LABELS[$state] ?? $state),
                TextColumn::make('cells_count')->label('الخلايا')->numeric(),
                ToggleColumn::make('active')->label('ظاهر'),
            ])
            ->filters([
                SelectFilter::make('pricing_compare_group_id')->label('المجموعة')
                    ->options(fn (): array => PricingCompareGroup::query()->orderBy('sort')->get()
                        ->mapWithKeys(fn (PricingCompareGroup $group): array => [$group->id => $group->title])->all()),
            ])
            ->reorderable('sort')
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
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
