<?php

namespace App\Filament\Admin\Resources\TikTokBrandAccountResource\Pages;

use App\Filament\Admin\Resources\TikTokBrandAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use App\Models\TikTokBrandAccount;
use App\Imports\TikTokBrandAccountsImport;
use App\Exports\TikTokBrandAccountsTemplateExport;
use Filament\Forms\Components\FileUpload;

class ListTikTokBrandAccounts extends ListRecords
{
    protected static string $resource = TikTokBrandAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scrapeAllFilteredRepostVideos')
                ->label('검색결과 전체 계정 리포스트 영상 스크랩')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('검색결과 전체 계정 리포스트 영상 스크랩')
                ->modalDescription('현재 필터링된 모든 브랜드 계정들의 리포스트 영상을 스크랩하시겠습니까?')
                ->modalSubmitActionLabel('스크랩 시작')
                ->action(function () {
                    try {
                        // 현재 필터링된 쿼리 가져오기
                        $query = $this->getFilteredTableQuery();
                        $usernames = $query->pluck('username')->toArray();

                        if (empty($usernames)) {
                            Notification::make()
                                ->title('실패')
                                ->body('필터링된 계정이 없습니다.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $apiUrl = config('app.api_url') . '/api/v1/tiktok/scrape_repost_video';

                        // API 요청 전송 (타임아웃 발생해도 무시)
                        try {
                            Http::timeout(3)->post($apiUrl, [
                                'usernames' => $usernames
                            ]);
                        } catch (\Illuminate\Http\Client\ConnectionException $e) {
                            // 타임아웃 발생 시 무시 (백그라운드 처리 중)
                        }

                        Notification::make()
                            ->title('요청 전송 완료')
                            ->body(count($usernames) . '개의 계정에 대한 리포스트 영상 스크랩 요청을 전송했습니다. 백그라운드에서 처리됩니다.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('오류')
                            ->body('스크랩 중 오류가 발생했습니다: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('importExcel')
                ->label('엑셀 일괄 등록')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('브랜드 계정 엑셀 일괄 등록')
                ->modalDescription('엑셀 파일을 업로드하여 브랜드 계정을 일괄 등록합니다.')
                ->modalSubmitActionLabel('업로드')
                ->extraModalFooterActions([
                    Action::make('downloadTemplate')
                        ->label('템플릿 다운로드')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function () {
                            $exporter = new TikTokBrandAccountsTemplateExport();
                            return $exporter->export();
                        }),
                ])
                ->form([
                    FileUpload::make('file')
                        ->label('엑셀 파일')
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ])
                        ->required()
                ])
                ->action(function (array $data) {
                    try {
                        // 업로드된 파일 처리
                        $uploadedFile = $data['file'];

                        // Livewire TemporaryUploadedFile 처리
                        if ($uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $path = $uploadedFile->getRealPath();
                        } else {
                            // 파일이 배열인 경우 첫 번째 파일 사용
                            if (is_array($uploadedFile)) {
                                $uploadedFile = reset($uploadedFile);
                            }

                            // storage 경로 체크
                            $possiblePaths = [
                                storage_path('app/' . $uploadedFile),
                                storage_path('app/public/' . $uploadedFile),
                                storage_path('app/livewire-tmp/' . $uploadedFile),
                            ];

                            $path = null;
                            foreach ($possiblePaths as $possiblePath) {
                                if (file_exists($possiblePath)) {
                                    $path = $possiblePath;
                                    break;
                                }
                            }

                            if (!$path) {
                                throw new \Exception('업로드된 파일을 찾을 수 없습니다: ' . $uploadedFile);
                            }
                        }

                        $import = new TikTokBrandAccountsImport();
                        $successCount = $import->import($path);

                        Notification::make()
                            ->title('성공')
                            ->body($successCount . '개의 브랜드 계정이 성공적으로 등록되었습니다.')
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $errors = $e->errors();
                        $errorMessage = '유효성 검사 실패: ';

                        if (isset($errors['import'])) {
                            $errorMessage .= implode(' ', $errors['import']);
                        } else {
                            $errorMessage .= $e->getMessage();
                        }

                        Notification::make()
                            ->title('검증 실패')
                            ->body($errorMessage)
                            ->danger()
                            ->duration(10000)
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('오류')
                            ->body('엑셀 업로드 중 오류가 발생했습니다: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }
}
