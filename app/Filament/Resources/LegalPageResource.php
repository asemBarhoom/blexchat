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

    protected static ?string $navigationLabel = 'الخصوصية والشروط';

    protected static ?string $modelLabel = 'صفحة قانونية';

    protected static ?string $pluralModelLabel = 'الخصوصية والشروط';

    protected static string|\UnitEnum|null $navigationGroup = HomeContentFields::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        $locales = config('app.supported_locales');

        return $schema->components([
            Section::make()->schema([
                Translations::input('title', 'العنوان'),
                DatePicker::make('effective_on')->label('آخر تحديث')
                    ->helperText('يظهر للزوار كتاريخ "آخر تحديث". غيّره عند تغيير مضمون الصفحة، لا عند تصحيح خطأ إملائي.'),
            ]),
            Section::make('النص')->description('Markdown: ## للعناوين، - للقوائم، **للخط العريض**.')->schema([
                Grid::make(count($locales))->schema(array_map(
                    fn (string $locale): MarkdownEditor => MarkdownEditor::make("body.{$locale}")
                        ->label('النص — '.Translations::localeName($locale))
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
                TextColumn::make('title')->label('العنوان'),
                TextColumn::make('slug')->label('الرابط')->formatStateUsing(fn (string $state): string => "/legal/{$state}")->badge()->color('gray'),
                TextColumn::make('effective_on')->label('آخر تحديث')->date(),
                TextColumn::make('updated_at')->label('آخر تعديل')->since(),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->recordActions([EditAction::make()->label('تعديل')]);
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
