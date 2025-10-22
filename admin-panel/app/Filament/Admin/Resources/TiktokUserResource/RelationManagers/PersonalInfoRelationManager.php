<?php

namespace App\Filament\Admin\Resources\TiktokUserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PersonalInfoRelationManager extends RelationManager
{
    protected static string $relationship = 'personalInfo';
    protected static ?string $title = '개인정보';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('이름')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('이메일')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('전화번호')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('주소')
                    ->maxLength(500),
                TextInput::make('country')
                    ->label('국가')
                    ->maxLength(255),
                Textarea::make('additional_info')
                    ->label('추가 정보')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('이름'),
                TextColumn::make('email')
                    ->label('이메일'),
                TextColumn::make('phone')
                    ->label('전화번호'),
                TextColumn::make('address')
                    ->label('주소')
                    ->limit(30),
                TextColumn::make('country')
                    ->label('국가'),
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