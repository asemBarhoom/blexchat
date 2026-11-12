<?php

namespace App\Filament\Resources\HelpArticles;

use App\Filament\Resources\HelpArticles\Pages\CreateHelpArticle;
use App\Filament\Resources\HelpArticles\Pages\EditHelpArticle;
use App\Filament\Resources\HelpArticles\Pages\ListHelpArticles;
use App\Filament\Resources\HelpArticles\Schemas\HelpArticleForm;
use App\Filament\Resources\HelpArticles\Tables\HelpArticlesTable;
use App\Models\HelpArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('admin/help.articles');
    }

    public static function getModelLabel(): string
    {
        return __('admin/help.article');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/help.articles');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_content');
    }

    public static function form(Schema $schema): Schema
    {
        return HelpArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpArticlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpArticles::route('/'),
            'create' => CreateHelpArticle::route('/create'),
            'edit' => EditHelpArticle::route('/{record}/edit'),
        ];
    }
}
