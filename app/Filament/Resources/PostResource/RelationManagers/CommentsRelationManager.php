<?php

namespace App\Filament\Resources\PostResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/common.comments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')->label(__('admin/posts.commenter'))->searchable(),
                TextColumn::make('text')->label(__('admin/posts.comment_text'))->limit(80)->wrap(),
                IconColumn::make('automated_reply')
                    ->label(__('admin/posts.auto_reply'))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('automation.name')
                    ->label(__('admin/posts.automation'))
                    ->default('—')
                    ->toggleable(),
                TextColumn::make('posted_at')->label(__('admin/posts.commented_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('automated_reply')
                    ->label(__('admin/posts.auto_replies'))
                    ->trueLabel(__('admin/posts.auto_only'))
                    ->falseLabel(__('admin/posts.manual_only'))
                    ->native(false),
            ])
            ->defaultSort('posted_at', 'desc');
    }
}
