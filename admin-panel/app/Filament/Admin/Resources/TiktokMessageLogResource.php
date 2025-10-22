<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokMessageLogResource\Pages;
use App\Filament\Admin\Resources\TiktokMessageLogResource\RelationManagers;
use App\Models\TiktokMessageLog;
use Filament\Forms;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TiktokMessageLogResource extends Resource
{
    protected static ?string $model = TiktokMessageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $label = '메시지 로그';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - 로그';
    protected static ?string $navigationLabel = '메시지 로그';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('tiktok_user_id')
                    ->relationship('tiktok_user', 'username')
                    ->label('사용자'),
                Select::make('tiktok_message_id')
                    ->relationship('tiktok_message', 'title')
                    ->label('메시지'),
                Select::make('message_text')
                    ->label('메시지 내용'),
                Select::make('tiktok_sender_id')
                    ->relationship('tiktok_sender', 'name')
                    ->label('발신 계정'),
                TextInput::make('result')
                    ->label('결과')
                    ->maxLength(255),
                Textarea::make('result_text')
                    ->label('결과 메시지')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                // 메시지 발송 완료된 것만 조회 (result가 있는 것)
                return $query->whereNotNull('result')->where('result', '!=', '');
            })
            ->columns([
                TextColumn::make('tiktok_user.username')
                    ->label('사용자')
                    ->sortable(),
                TextColumn::make('tiktok_message.title')
                    ->label('메시지')
                    ->sortable(),
                TextColumn::make('message_text')
                    ->label('메시지 내용')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    })
                    ->wrap(),
                TextColumn::make('tiktok_sender.name')
                    ->label('발신 계정')
                    ->sortable(),
                TextColumn::make('result')
                    ->label('결과')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label('전송상태')
                    ->options([
                        'success' => '전송성공',
                        'pending' => '전송대기',
                        'failed' => '전송실패',
                        'all' => '전체'
                    ])
                    ->default('success')
                    ->query(function (Builder $query, array $data): Builder {
                        return match($data['value'] ?? 'success') {
                            'success' => $query->whereNotNull('result')->where('result', '!=', ''),
                            'failed' => $query->whereNotNull('result')->where('result', '!=', ''),
                            'pending' => $query->where(function ($q) {
                                $q->whereNull('result')->orWhere('result', '');
                            }),
                            'all' => $query,
                            default => $query->whereNotNull('result')->where('result', '!=', ''),
                        };
                    })
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
            'index' => Pages\ListTiktokMessageLogs::route('/'),
            'create' => Pages\CreateTiktokMessageLog::route('/create'),
            'edit' => Pages\EditTiktokMessageLog::route('/{record}/edit'),
        ];
    }
}
