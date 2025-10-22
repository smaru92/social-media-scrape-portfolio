<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokVideoResource\Pages;
use App\Models\TiktokVideo;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class TiktokVideoResource extends Resource
{
    protected static ?string $model = TiktokVideo::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $label = '동영상';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - 로그';
    protected static ?string $navigationLabel = '동영상';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('tiktok_user_id')
                    ->label('사용자')
                    ->relationship('tiktokUser', 'username', function ($query) {
                        return $query->orderBy('nickname', 'asc');
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->username . ($record->nickname ? " ({$record->nickname})" : ''))
                    ->required()
                    ->searchable()
                    ->preload(),
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
                TextInput::make('share_count')
                    ->label('공유 수')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('posted_at', 'desc')
            ->columns([
                TextColumn::make('tiktokUser.username')
                    ->label('사용자')
                    ->formatStateUsing(fn ($record) => $record->tiktokUser->username . ($record->tiktokUser->nickname ? " ({$record->tiktokUser->nickname})" : ''))
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('thumbnail_url')
                    ->label('썸네일')
                    ->height(60)
                    ->width(80)
                    ->getStateUsing(function ($record) {
                        if ($record->thumbnail_url && !str_starts_with($record->thumbnail_url, 'http')) {
                            return asset('storage/' . $record->thumbnail_url);
                        }
                        return $record->thumbnail_url;
                    }),
                TextColumn::make('title')
                    ->label('제목')
                    ->limit(50)
                    ->searchable(),
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
                TextColumn::make('video_url')
                    ->label('동영상 주소')
                    ->url(fn ($record) => $record->video_url)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListTiktokVideos::route('/'),
            'create' => Pages\CreateTiktokVideo::route('/create'),
            'edit' => Pages\EditTiktokVideo::route('/{record}/edit'),
        ];
    }
}
