<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokUserLogResource\Pages;
use App\Filament\Admin\Resources\TiktokUserLogResource\RelationManagers;
use App\Models\TiktokUserLog;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TiktokUserLogResource extends Resource
{
    protected static ?string $model = TiktokUserLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $label = '사용자 로그';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - 로그';
    protected static ?string $navigationLabel = '사용자 집계';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('keyword')
                    ->label('키워드')
                    ->required()
                    ->maxLength(255),
                TextInput::make('min_followers')
                    ->label('최소 팔로워')
                    ->required()
                    ->maxLength(255),
                Textarea::make('search_user_count')
                    ->label('탐지된 사용자')
                    ->required(),
                Textarea::make('save_user_count')
                    ->label('저장한 사용자')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('검색 키워드')
                    ->searchable(),
                TextColumn::make('min_followers')
                    ->label('최소 팔로워')
                    ->sortable(),
                TextColumn::make('search_user_count')
                    ->label('탐지된 사용자')
                    ->sortable(),
                TextColumn::make('save_user_count')
                    ->label('저장한 사용자')
                    ->sortable(),
                IconColumn::make('is_error')
                    ->label('에러 발생 여부')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('최근수정일')
                    ->sortable(),
            ])
            ->filters([
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
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTiktokUserLogs::route('/'),
            'create' => Pages\CreateTiktokUserLog::route('/create'),
            'edit' => Pages\EditTiktokUserLog::route('/{record}/edit'),
        ];
    }
}
