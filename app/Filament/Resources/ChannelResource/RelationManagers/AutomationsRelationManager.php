<?php

namespace App\Filament\Resources\ChannelResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationsRelationManager extends RelationManager
{
    protected static string $relationship = 'automations';

    protected static ?string $title = 'الأتمتة';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم'),
                TextColumn::make('trigger_type')
                    ->label('المشغّل')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => 'تعليق',
                        'dm_keyword'      => 'DM',
                        'story_mention'   => 'ستوري',
                        default           => $state,
                    })
                    ->badge()->color('info'),
                IconColumn::make('active')->label('مفعّلة')->boolean(),
            ]);
    }
}
