<?php

namespace App\Filament\Admin\Resources\TiktokSenderResource\Pages;

use App\Filament\Admin\Resources\TiktokSenderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use App\Models\TiktokSender;

class ListTiktokSenders extends ListRecords
{
    protected static string $resource = TiktokSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload_session_file')
                ->label('세션 파일 업로드')
                ->form([
                    Select::make('sender_id')
                        ->label('발신 계정 선택')
                        ->options(TiktokSender::pluck('nickname', 'id'))
                        ->required()
                        ->searchable(),
                    FileUpload::make('session_file')
                        ->label('세션 파일')
                        ->helperText('크롬 확장프로그램 "Export cookie JSON file for Puppeteer"를 사용하여 추출한 JSON 파일을 업로드하세요. 
                        확장프로그램 설치: https://chromewebstore.google.com/detail/export-cookie-json-file-f/nmckokihipjgplolmcmjakknndddifde')
                        ->acceptedFileTypes(['application/json'])
                        ->directory('tiktok_sessions')
                        ->disk('public')
                        ->visibility('private')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $record = TiktokSender::find($data['sender_id']);
                        $uploadedFilePath = $data['session_file'];
                        $fullPath = storage_path('app/public/' . $uploadedFilePath);

                        if (!file_exists($fullPath)) {
                            throw new \Exception("업로드된 파일을 찾을 수 없습니다: " . $fullPath);
                        }

                        $fileContent = file_get_contents($fullPath);
                        $sessionData = json_decode($fileContent, true);

                        if ($sessionData === null) {
                            throw new \Exception("JSON 파일 형식이 올바르지 않습니다.");
                        }

                        $convertedData = TiktokSenderResource::convertSessionFormat($sessionData);
                        file_put_contents($fullPath, json_encode($convertedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                        $uploadResult = TiktokSenderResource::uploadSessionToApi($record->id, $convertedData);
                        
                        if (!$uploadResult['success']) {
                            throw new \Exception("API 서버로 파일 업로드 실패: " . $uploadResult['message']);
                        }

                        $record->session_file_path = $uploadResult['api_file_path'];
                        $record->session_updated_at = now();
                        $record->save();

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
                }),
            Actions\CreateAction::make(),
        ];
    }
}
