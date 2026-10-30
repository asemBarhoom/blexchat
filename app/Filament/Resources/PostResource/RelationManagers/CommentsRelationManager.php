<?php

namespace App\Filament\Resources\PostResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'التعليقات';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')->label('المُعلِّق')->searchable(),
                TextColumn::make('text')->label('التعليق')->limit(80)->wrap(),
                IconColumn::make('automated_reply')
                    ->label('رد تلقائي')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('automation.name')
                    ->label('قاعدة الأتمتة')
                    ->default('—')
                    ->toggleable(),
                TextColumn::make('posted_at')->label('وقت النشر')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('automated_reply')
                    ->label('الردود التلقائية')
                    ->trueLabel('تلقائية فقط')
                    ->falseLabel('يدوية فقط')
                    ->native(false),
            ])
            ->defaultSort('posted_at', 'desc');
    }
}
