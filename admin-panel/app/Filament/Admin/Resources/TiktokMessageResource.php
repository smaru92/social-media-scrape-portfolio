<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokMessageResource\Pages;
use App\Filament\Admin\Resources\TiktokMessageResource\RelationManagers;
use App\Models\TiktokMessage;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class TiktokMessageResource extends Resource
{
    protected static ?string $model = TiktokMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '메시지';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - DM전송';
    protected static ?string $navigationLabel = '메시지';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('기본 정보')
                    ->schema([
                        TextInput::make('title')
                            ->label('제목')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('tiktok_sender_id')
                            ->relationship('tiktok_sender', 'name')
                            ->label('발신 계정')
                            ->required()
                            ->columnSpan(1),
                        Select::make('tiktok_message_template_id')
                            ->relationship('tiktok_message_template', 'title')
                            ->label('메시지 템플릿')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('수신자')
                    ->schema([
                        Select::make('tiktok_users')
                            ->relationship('tiktok_users', 'nickname')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('틱톡 사용자'),
                    ]),

                Forms\Components\Section::make('발송 설정')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('is_auto')
                                    ->label('자동 발송')
                                    ->default(true)
                                    ->reactive()
                                    ->required()
                                    ->inline(false),
                                Toggle::make('is_complete')
                                    ->label('전송 완료')
                                    ->required()
                                    ->inline(false),
                            ]),
                        Select::make('send_status')
                            ->label('전송 상태')
                            ->options([
                                'pending' => '미전송',
                                'sending' => '전송중',
                                'completed' => '전송완료',
                            ])
                            ->default('pending')
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('success_count')
                                    ->label('전송 성공')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('명')
                                    ->disabled(),
                                TextInput::make('fail_count')
                                    ->label('전송 실패')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('명')
                                    ->disabled(),
                            ])
                            ->columnSpan(1),
                        DateTimePicker::make('start_at')
                            ->label('전송 시작 시간')
                            ->visible(fn (callable $get) => $get('is_auto'))
                            ->columnSpan(1),
                        DateTimePicker::make('end_at')
                            ->label('전송 종료 시간')
                            ->visible(fn (callable $get) => $get('is_auto'))
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tiktok_sender.name')
                    ->label('발신 계정')
                    ->sortable(),
                TextColumn::make('tiktok_message_template.title')
                    ->label('메시지 템플릿')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('제목')
                    ->searchable(),
                IconColumn::make('is_auto')
                    ->label('자동 발송')
                    ->boolean(),
                IconColumn::make('is_complete')
                    ->label('전송 완료')
                    ->boolean(),
                TextColumn::make('send_status')
                    ->label('전송 상태')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sending' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '미전송',
                        'sending' => '전송중',
                        'completed' => '전송완료',
                        default => $state,
                    }),
                TextColumn::make('tiktok_users_count')
                    ->label('대상')
                    ->counts('tiktok_users')
                    ->suffix('명')
                    ->color('info'),
                TextColumn::make('success_count')
                    ->label('성공')
                    ->suffix('명')
                    ->color('success')
                    ->toggleable(),
                TextColumn::make('fail_count')
                    ->label('실패')
                    ->suffix('명')
                    ->color('danger')
                    ->toggleable(),
                TextColumn::make('start_at')
                    ->label('전송 시작 시간')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_at')
                    ->label('전송 종료 시간')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('manual_send')
                    ->label('수동 발송')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => !$record->is_complete)
                    ->requiresConfirmation()
                    ->modalHeading('메시지 수동 발송')
                    ->modalDescription(fn ($record) => $record->is_auto
                        ? '자동 설정된 메시지를 수동으로 발송하시겠습니까? 발송 후 데이터 타입이 수동으로 변경됩니다.'
                        : '선택한 메시지를 지금 발송하시겠습니까?')
                    ->action(function ($record) {
                        // 자동 메시지인 경우 수동으로 변경
                        if ($record->is_auto) {
                            $record->update(['is_auto' => false]);
                        }

                        // 발신 계정의 세션 파일 경로 가져오기
                        $sender = $record->tiktok_sender;
                        $sessionFilePath = $sender->session_file_path ?? null;

                        // 템플릿 정보 가져오기
                        $template = $record->tiktok_message_template;

                        // 사용자 목록 가져오기 (username 또는 nickname 필드 확인)
                        $usernames = $record->tiktok_users()->pluck('username')->filter()->toArray();
                        if (empty($usernames)) {
                            $usernames = $record->tiktok_users()->pluck('nickname')->filter()->toArray();
                        }

                        // 필수 데이터 검증
                        if (empty($usernames)) {
                            throw new \Exception('수신자가 설정되지 않았습니다.');
                        }

                        // API 호출 데이터 구성 (Python FastAPI 스키마에 맞춤)
                        $apiData = [
                            'usernames' => $usernames,
                            'template_code' => $template->template_code, // 필드명 수정
                            'session_file_path' => $sessionFilePath,
                            'message_id' => $record->id,
                        ];

                        try {
                            $apiUrl = config('app.api_url') . '/api/v1/tiktok/send_message';

                            // 일반 JSON POST 요청으로 전송 (멀티파트 대신)
                            $response = Http::timeout(15)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post($apiUrl, $apiData);

                            // 로깅을 위한 응답 내용 확인
                            \Log::info('TikTok API Response', [
                                'status' => $response->status(),
                                'body' => $response->body(),
                                'data_sent' => $apiData
                            ]);

                            if ($response->successful()) {
                                Notification::make()
                                    ->title('발송 요청 성공')
                                    ->body('메시지 발송이 시작되었습니다.')
                                    ->success()
                                    ->send();
                            } else {
                                $errorBody = $response->body();
                                Notification::make()
                                    ->title('발송 요청 실패')
                                    ->body("메시지 발송 요청에 실패했습니다. (HTTP {$response->status()}): " . substr($errorBody, 0, 200))
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('발송 요청 처리 중')
                                ->body('메시지 발송이 백그라운드에서 처리됩니다. 잠시 후 결과를 확인해주세요.')
                                ->info()
                                ->send();
                        }
                    }),
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
            'index' => Pages\ListTiktokMessages::route('/'),
            'create' => Pages\CreateTiktokMessage::route('/create'),
            'edit' => Pages\EditTiktokMessage::route('/{record}/edit'),
        ];
    }
}
