<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokMessageTemplateResource\Pages;
use App\Filament\Admin\Resources\TiktokMessageTemplateResource\RelationManagers;
use App\Models\TiktokMessageTemplate;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TiktokMessageTemplateResource extends Resource
{
    protected static ?string $model = TiktokMessageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';


    protected static ?string $label = '메시지 템플릿';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - DM전송';
    protected static ?string $navigationLabel = '메시지 템플릿';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('제목')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('template_code')
                    ->label('템플릿코드')
                    ->required()
                    ->maxLength(255)
                    // 정규식 유효성 검사: 영어와 언더바만 허용
                    ->rule('regex:/^[A-Za-z_]+$/')
                    // 입력 도중 제한하려면 mask 사용 가능
                    ->placeholder('영문과 언더바만 입력')
                    ->helperText('영문자와 언더바(_)만 입력 가능합니다.')
                    ->columnSpanFull(),
                Repeater::make('message_header_json')
                    ->label('메시지 헤더')
                    ->schema([
                        Textarea::make('text')
                            ->label('헤더 텍스트')
                            ->rows(3)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                    ->columnSpanFull(),
                Repeater::make('message_body_json')
                    ->label('메시지 본문')
                    ->schema([
                        Textarea::make('text')
                            ->label('본문 텍스트')
                            ->rows(5)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                    ->columnSpanFull(),
                Repeater::make('message_footer_json')
                    ->label('메시지 푸터')
                    ->schema([
                        Textarea::make('text')
                            ->label('푸터 텍스트')
                            ->rows(3)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('템플릿명')
                    ->searchable(),
                TextColumn::make('template_code')
                    ->label('템플릿 코드')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message_header_json')
                    ->label('헤더 템플릿')
                    ->getStateUsing(fn ($record) => !empty($record->message_body_json) ? count($record->message_body_json) . '개' : '0개')
                    ->badge()
                    ->color('info'),
                TextColumn::make('message_body_json')
                    ->label('본문 템플릿')
                    ->getStateUsing(fn ($record) => !empty($record->message_body_json) ? count($record->message_body_json) . '개' : '0개')
                    ->badge()
                    ->color('success'),
                TextColumn::make('message_footer_json')
                    ->label('푸터 템플릿')
                    ->getStateUsing(fn ($record) => !empty($record->message_footer_json) ? count($record->message_footer_json) . '개' : '0개')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->searchable(),
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
            'index' => Pages\ListTiktokMessageTemplates::route('/'),
            'create' => Pages\CreateTiktokMessageTemplate::route('/create'),
            'edit' => Pages\EditTiktokMessageTemplate::route('/{record}/edit'),
        ];
    }
}
