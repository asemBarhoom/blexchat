<?php

namespace App\Filament\Resources\HelpArticles\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HelpArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::input('title', __('admin/common.title')),
                Translations::input('body', __('admin/common.text'), long: true),
                TextInput::make('category')->label(__('admin/help.category'))->required()->default('general'),
                TextInput::make('sort')->label(__('admin/common.sort'))->numeric()->integer()->minValue(0)->default(0)->required(),
                Toggle::make('active')->label(__('admin/common.active'))->default(true),
            ]);
    }
}
