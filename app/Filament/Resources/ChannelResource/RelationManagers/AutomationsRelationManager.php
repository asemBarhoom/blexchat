<?php

namespace App\Filament\Resources\ChannelResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AutomationsRelationManager extends RelationManager
{
    protected static string $relationship = 'automations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/common.automations');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin/common.name')),
                TextColumn::make('trigger_type')
                    ->label(__('admin/common.trigger'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'comment_keyword' => __('admin/common.comment'),
                        'dm_keyword' => 'DM',
                        'story_mention' => __('admin/channels.story'),
                        default => $state,
                    })
                    ->badge()->color('info'),
                IconColumn::make('active')->label(__('admin/common.active'))->boolean(),
            ]);
    }
}
