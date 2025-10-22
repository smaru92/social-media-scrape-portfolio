<?php

namespace App\Filament\Admin\Resources\TiktokUserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class VideosRelationManager extends RelationManager
{
    protected static string $relationship = 'videos';
    protected static ?string $title = '동영상';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('video_url')
                    ->label('동영상 주소')
                    ->url()
                    ->required()
                    ->maxLength(255),
                TextInput::make('title')
                    ->label('제목')
                    ->required()
                    ->maxLength(255),
                TextInput::make('thumbnail_url')
                    ->label('썸네일 주소')
                    ->url()
                    ->maxLength(255),
                TextInput::make('view_count')
                    ->label('조회수')
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('posted_at')
                    ->label('게시일'),
                TextInput::make('like_count')
                    ->label('좋아요수')
                    ->numeric()
                    ->default(0),
                TextInput::make('comment_count')
                    ->label('댓글 수')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('posted_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('썸네일')
                    ->height(60)
                    ->width(80),
                TextColumn::make('title')
                    ->label('제목')
                    ->limit(30),
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
                TextColumn::make('posted_at')
                    ->label('게시일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('video_url')
                    ->label('동영상')
                    ->url(fn ($record) => $record->video_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => '보기')
                    ->color('primary'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}