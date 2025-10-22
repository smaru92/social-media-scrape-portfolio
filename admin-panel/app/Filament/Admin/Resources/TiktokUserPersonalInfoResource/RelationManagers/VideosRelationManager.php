<?php

namespace App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class VideosRelationManager extends RelationManager
{
    protected static string $relationship = 'videos';
    protected static ?string $title = '동영상';
    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('제목')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('제목')
                    ->searchable()
                    ->limit(50),
                ImageColumn::make('thumbnail_url')
                    ->label('썸네일')
                    ->height(60)
                    ->width(80),
                TextColumn::make('video_url')
                    ->label('동영상')
                    ->url(fn ($state) => $state)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => '보기')
                    ->color('primary'),
                TextColumn::make('view_count')
                    ->label('조회수')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('like_count')
                    ->label('좋아요')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comment_count')
                    ->label('댓글')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('share_count')
                    ->label('공유')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('posted_at')
                    ->label('게시일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}