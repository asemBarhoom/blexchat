<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalPageResource\Pages;
use App\Filament\Support\HomeContentFields;
use App\Filament\Support\Translations;
use App\Models\LegalPage;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The Privacy Policy and the Terms of Service. Both are seeded and only edited here:
 * the frontend links to exactly these two, so none is added or deleted.
 */
class LegalPageResource extends Resource
{
    protected static ?string $model = LegalPage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    public static function getNavigationLabel(): string
    {
        return __('admin/home.privacy_and_terms');
    }

    public static function getModelLabel(): string
    {
        return __('admin/home.legal_page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/home.privacy_and_terms');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return HomeContentFields::homeGroup();
    }

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        $locales = config('app.supported_locales');

        return $schema->components([
            Section::make()->schema([
                Translations::input('title', __('admin/common.title')),
                DatePicker::make('effective_on')->label(__('admin/home.last_updated'))
                    ->helperText(__('admin/home.visitors_see_it_as_the_last_updated_date')),
            ]),
            Section::make(__('admin/common.text'))->description(__('admin/home.markdown_for_headings_for_lists_for_bold'))->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): MarkdownEditor => MarkdownEditor::make("body.{$locale}")
                        ->label(__('admin/home.text_in', ['locale' => Translations::localeName($locale)]))
                        ->required(Translations::isFallback($locale))
                        ->toolbarButtons([['heading', 'bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                        ->extraAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
                    $locales,
                )),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('admin/common.title')),
                TextColumn::make('slug')->label(__('admin/common.link'))->formatStateUsing(fn (string $state): string => "/legal/{$state}")->badge()->color('gray'),
                TextColumn::make('effective_on')->label(__('admin/home.last_updated'))->date(),
                TextColumn::make('updated_at')->label(__('admin/common.updated_at'))->since(),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->recordActions([EditAction::make()->label(__('admin/common.edit'))]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalPages::route('/'),
            'edit' => Pages\EditLegalPage::route('/{record}/edit'),
        ];
    }
}
