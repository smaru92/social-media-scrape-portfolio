<?php

namespace App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;

class UploadRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'uploadRequests';
    protected static ?string $title = '업로드 요청';
    protected static ?string $recordTitleAttribute = 'request_content';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('request_content')
                    ->label('요청사항')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TagsInput::make('request_tags')
                    ->label('필수 해시태그/멘션')
                    ->placeholder('#해시태그 @멘션 등을 입력')
                    ->helperText('공백으로 구분하여 여러 개를 입력할 수 있습니다.')
                    ->separator(' ')
                    ->columnSpanFull(),
                DateTimePicker::make('requested_at')
                    ->label('요청일시')
                    ->required()
                    ->default(now()),
                Forms\Components\DatePicker::make('deadline_date')
                    ->label('게시 기한')
                    ->displayFormat('Y-m-d'),
                Toggle::make('is_uploaded')
                    ->label('업로드 여부')
                    ->default(false)
                    ->reactive(),
                Toggle::make('is_confirm')
                    ->label('최종 확인')
                    ->default(false)
                    ->helperText('담당자 최종 확인여부'),
                TextInput::make('upload_url')
                    ->label('업로드 URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('is_uploaded')),
                TextInput::make('upload_thumbnail_url')
                    ->label('업로드 썸네일 URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('is_uploaded')),
                DateTimePicker::make('uploaded_at')
                    ->label('업로드 일시')
                    ->visible(fn ($get) => $get('is_uploaded')),
                Select::make('tiktok_video_id')
                    ->label('연결된 동영상')
                    ->relationship('tiktokVideo', 'title', fn ($query) =>
                        $query->where('tiktok_user_id', $this->ownerRecord->tiktok_user_id)
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('is_uploaded')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('request_content')
                    ->label('요청사항')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('request_tags')
                    ->label('태그')
                    ->badge()
                    ->separator(' ')
                    ->searchable(),
                TextColumn::make('requested_at')
                    ->label('요청일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('deadline_date')
                    ->label('게시 기한')
                    ->date('Y-m-d')
                    ->sortable()
                    ->color(function ($state, $record) {
                        if (!$record || !$state) {
                            return 'gray';
                        }
                        if (!$record->is_uploaded && $record->deadline_date->isPast()) {
                            return 'danger';
                        }
                        if (!$record->is_uploaded && $record->deadline_date->diffInDays(now()) <= 3) {
                            return 'warning';
                        }
                        return 'gray';
                    }),
                IconColumn::make('is_uploaded')
                    ->label('업로드')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),
                IconColumn::make('is_confirm')
                    ->label('확인')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),
                ImageColumn::make('upload_thumbnail_url')
                    ->label('썸네일')
                    ->height(60)
                    ->width(80)
                    ->visible(function ($record) {
                        return $record && $record->is_uploaded;
                    }),
                TextColumn::make('upload_url')
                    ->label('동영상')
                    ->url(function ($state) {
                        return $state;
                    })
                    ->openUrlInNewTab()
                    ->formatStateUsing(function ($state) {
                        return $state ? '보기' : '-';
                    })
                    ->color(function ($state) {
                        return $state ? 'primary' : 'gray';
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
