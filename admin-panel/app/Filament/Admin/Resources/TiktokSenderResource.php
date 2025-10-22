<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokSenderResource\Pages;
use App\Filament\Admin\Resources\TiktokSenderResource\RelationManagers;
use App\Models\TiktokSender;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
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
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TiktokSenderResource extends Resource
{
    protected static ?string $model = TiktokSender::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '발신계정';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - DM전송';
    protected static ?string $navigationLabel = '발신계정';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('계정 정보')
                    ->schema([
                        TextInput::make('nickname')
                            ->label('별칭')
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('계정이름')
                            ->maxLength(255),
                        TextInput::make('login_id')
                            ->label('로그인 아이디')
                            ->maxLength(255),
                        TextInput::make('login_password')
                            ->label('로그인 패스워드')
                            ->password()
                            ->maxLength(255),
                        TextInput::make('sort')
                            ->label('정렬')
                            ->numeric(),
                    ]),
                Section::make('세션 관리')
                    ->schema([
                        TextInput::make('session_file_path')
                            ->label('세션 파일 경로')
                            ->maxLength(255)
                            ->readOnly(),
                        FileUpload::make('session_file')
                            ->label('새 세션 파일 업로드')
                            ->helperText('크롬 확장프로그램 "Export cookie JSON file for Puppeteer"를 사용하여 추출한 JSON 파일을 업로드하세요.
                            확장프로그램 설치: https://chromewebstore.google.com/detail/export-cookie-json-file-f/nmckokihipjgplolmcmjakknndddifde')
                            ->acceptedFileTypes(['application/json'])
                            ->directory('tiktok_sessions')
                            ->disk('public')
                            ->visibility('private')
                            ->afterStateUpdated(function ($state, $record) {
                                if (!$state || !$record) {
                                    return;
                                }

                                try {
                                    $fullPath = storage_path('app/public/' . $state);

                                    if (!file_exists($fullPath)) {
                                        throw new \Exception("업로드된 파일을 찾을 수 없습니다.");
                                    }

                                    $fileContent = file_get_contents($fullPath);
                                    $sessionData = json_decode($fileContent, true);

                                    if ($sessionData === null) {
                                        throw new \Exception("JSON 파일 형식이 올바르지 않습니다.");
                                    }

                                    $convertedData = self::convertSessionFormat($sessionData);
                                    file_put_contents($fullPath, json_encode($convertedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                                    // session_file_path가 있으면 그 파일명 사용, 없으면 새로 생성
                                    if (!empty($record->session_file_path)) {
                                        $fileName = basename($record->session_file_path);
                                    } else {
                                        $fileName = 'sender_' . $record->id . '_' . time() . '.json';
                                    }

                                    // 로컬 파일 경로 저장
                                    $permanentPath = 'tiktok_sessions/' . $fileName;
                                    $permanentFullPath = storage_path('app/public/' . $permanentPath);

                                    // 파일을 영구 위치로 복사
                                    copy($fullPath, $permanentFullPath);

                                    // API 서버로 업로드
                                    $uploadResult = self::uploadSessionToApi($record->id, $convertedData, $fileName);

                                    if (!$uploadResult['success']) {
                                        // API 업로드 실패 시 로컬 파일 삭제
                                        if (file_exists($permanentFullPath)) {
                                            unlink($permanentFullPath);
                                        }
                                        throw new \Exception("API 서버로 파일 업로드 실패: " . $uploadResult['message']);
                                    }

                                    $record->session_file_path = $permanentPath; // 로컬 파일 경로 저장
                                    $record->session_updated_at = now();
                                    $record->save();

                                    // 임시 파일만 삭제
                                    if (file_exists($fullPath)) {
                                        unlink($fullPath);
                                    }

                                    Notification::make()
                                        ->title('세션 파일 업로드 및 API 전송 성공')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('세션 파일 업로드 실패')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                            ->dehydrated(false), // 폼 제출 시 이 필드 제외
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nickname')
                    ->label('별칭')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('계정이름')
                    ->searchable(),
                TextColumn::make('sort')
                    ->label('정렬')
                    ->sortable(),
                TextColumn::make('session_file_path')
                    ->label('세션 파일 경로')
                    ->toggleable(isToggledHiddenByDefault: false), // 항상 보이도록 설정
                TextColumn::make('session_updated_at')
                    ->label('세션 갱신일')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false), // 항상 보이도록 설정
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false), // 항상 보이도록 설정
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download_session_file')
                    ->label('세션 파일 다운로드')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (TiktokSender $record): bool => !empty($record->session_file_path) && Storage::disk('public')->exists($record->session_file_path))
                    ->action(function (TiktokSender $record) {
                        $filePath = Storage::disk('public')->path($record->session_file_path);
                        $fileName = 'tiktok_session_' . $record->id . '_' . date('Y-m-d_H-i-s') . '.json';

                        return response()->download($filePath, $fileName, [
                            'Content-Type' => 'application/json',
                            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
                        ]);
                    }),
                Action::make('upload_session_file')
                    ->label('세션 파일 업로드')
                    ->form([
                        FileUpload::make('session_file')
                            ->label('세션 파일')
                            ->helperText('크롬 확장프로그램 "Export cookie JSON file for Puppeteer"를 사용하여 추출한 JSON 파일을 업로드하세요.
                            확장프로그램 설치: https://chromewebstore.google.com/detail/export-cookie-json-file-f/nmckokihipjgplolmcmjakknndddifde')
                            ->acceptedFileTypes(['application/json']) // JSON 파일만 허용
                            ->directory('tiktok_sessions') // storage/app/public/tiktok_sessions 에 저장
                            ->disk('public') // public 디스크 명시적 지정
                            ->visibility('private') // 파일을 비공개로 설정
                            ->required(),
                    ])
                    ->action(function (TiktokSender $record, array $data) {
                        try {
                            $uploadedFilePath = $data['session_file']; // Filament가 반환하는 저장 경로

                            // public 디스크를 사용하므로 storage/app/public/ 경로 사용
                            $fullPath = storage_path('app/public/' . $uploadedFilePath);

                            // 파일이 존재하는지 확인
                            if (!file_exists($fullPath)) {
                                throw new \Exception("업로드된 파일을 찾을 수 없습니다: " . $fullPath);
                            }

                            // 업로드된 파일 읽기
                            $fileContent = file_get_contents($fullPath);
                            $sessionData = json_decode($fileContent, true);

                            if ($sessionData === null) {
                                throw new \Exception("JSON 파일 형식이 올바르지 않습니다.");
                            }

                            // 세션 데이터 형식 변환
                            $convertedData = self::convertSessionFormat($sessionData);

                            // 변환된 데이터를 파일에 다시 쓰기
                            file_put_contents($fullPath, json_encode($convertedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                            // session_file_path가 있으면 그 파일명 사용, 없으면 새로 생성
                            if (!empty($record->session_file_path)) {
                                $fileName = basename($record->session_file_path);
                            } else {
                                $fileName = 'sender_' . $record->id . '_' . time() . '.json';
                            }

                            // Python API 서버로 세션 파일 업로드
                            // 로컬 파일 경로 저장
                            $permanentPath = 'tiktok_sessions/' . $fileName;
                            $permanentFullPath = storage_path('app/public/' . $permanentPath);

                            // 파일을 영구 위치로 이동
                            rename($fullPath, $permanentFullPath);

                            // API 서버로 업로드
                            $uploadResult = self::uploadSessionToApi($record->id, $convertedData, $fileName);

                            if (!$uploadResult['success']) {
                                // API 업로드 실패 시 로컬 파일 삭제
                                if (file_exists($permanentFullPath)) {
                                    unlink($permanentFullPath);
                                }
                                throw new \Exception("API 서버로 파일 업로드 실패: " . $uploadResult['message']);
                            }

                            $record->session_file_path = $permanentPath; // 로컬 파일 경로 저장
                            $record->session_updated_at = now();
                            $record->save();

                            Notification::make()
                                ->title('세션 파일 업로드 및 API 전송 성공')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('세션 파일 업로드 실패')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
            'index' => Pages\ListTiktokSenders::route('/'),
            'create' => Pages\CreateTiktokSender::route('/create'),
            'edit' => Pages\EditTiktokSender::route('/{record}/edit'),
        ];
    }

    /**
     * 세션 파일 형식 변환
     * 쿠키 배열 형식을 TikTok에서 사용하는 형식으로 변환
     */
    public static function convertSessionFormat($sessionData)
    {
        // 이미 올바른 형식인 경우 (cookies와 origins 키가 있는 경우)
        if (isset($sessionData['cookies']) && isset($sessionData['origins'])) {
            return $sessionData;
        }

        // 쿠키 배열 형식인 경우 변환
        if (is_array($sessionData) && !empty($sessionData)) {
            // 첫 번째 요소가 쿠키 객체인지 확인
            $firstElement = reset($sessionData);
            if (isset($firstElement['name']) && isset($firstElement['value'])) {
                // 쿠키 배열을 올바른 형식으로 변환
                $convertedData = [
                    'cookies' => array_map(function($cookie) {
                        // sameSite 속성 추가 (없는 경우)
                        if (!isset($cookie['sameSite'])) {
                            if ($cookie['secure'] ?? false) {
                                $cookie['sameSite'] = 'None';
                            } else {
                                $cookie['sameSite'] = 'Lax';
                            }
                        }
                        return $cookie;
                    }, $sessionData),
                    'origins' => [
                        [
                            'origin' => 'https://www.tiktok.com',
                            'localStorage' => []
                        ]
                    ]
                ];
                return $convertedData;
            }
        }

        // 변환할 수 없는 형식인 경우 그대로 반환
        return $sessionData;
    }

    /**
     * Python API 서버로 세션 파일 업로드
     */
    public static function uploadSessionToApi($senderId, $sessionData, $originalFileName = null)
    {
        try {
            $apiUrl = config('app.api_url') . '/api/v1/tiktok/upload_session';

            // 원본 파일명이 제공되면 그대로 사용, 아니면 기본 형식 사용
            $fileName = $originalFileName ?? ('tiktok_session_' . $senderId . '.json');

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl, [
                    'sender_id' => $senderId,
                    'file_name' => $fileName,
                    'session_data' => $sessionData
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'api_file_path' => $result['file_path'] ?? $fileName,
                    'message' => 'Session file uploaded to API server successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'API responded with status ' . $response->status() . ': ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload to API server: ' . $e->getMessage()
            ];
        }
    }
}
