<?php

namespace App\Filament\Admin\Resources\TiktokUploadRequestResource\Pages;

use App\Filament\Admin\Resources\TiktokUploadRequestResource;
use App\Models\TiktokUploadRequest;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class ListTiktokUploadRequests extends ListRecords
{
    protected static string $resource = TiktokUploadRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadCheck')
                ->label('업로드 체크')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->action(function () {
                    try {
                        $apiUrl = rtrim(config('app.api_url'), '/') . '/api/v1/tiktok/upload_check';
                        
                        Http::post($apiUrl);
                        
                        Notification::make()
                            ->success()
                            ->title('업로드 체크 시작')
                            ->body('업로드 체크가 백그라운드에서 진행됩니다.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('오류 발생')
                            ->body('업로드 체크 중 오류가 발생했습니다: ' . $e->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('scrapeAllFilteredVideos')
                ->label('검색 결과 사용자 전체 비디오 스크랩')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('검색 결과 전체 비디오 스크랩')
                ->modalDescription(function () {
                    $query = $this->getFilteredTableQuery();
                    $usernames = $query->with('tiktokUser')
                        ->where('is_uploaded', false) // 업로드 안된 것만
                        ->where(function($q) {
                            $q->whereNull('deadline_date')
                              ->orWhere('deadline_date', '>', now()); // 기한 안 지난 것만
                        })
                        ->get()
                        ->pluck('tiktokUser.username')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    $count = count($usernames);
                    $usernameList = implode(', ', array_slice($usernames, 0, 5));
                    if ($count > 5) {
                        $usernameList .= ' 외 ' . ($count - 5) . '명';
                    }

                    return "현재 필터와 검색 조건에 맞는 {$count}명의 사용자 비디오를 스크랩합니다: {$usernameList}";
                })
                ->action(function () {
                    $query = $this->getFilteredTableQuery();
                    $usernames = $query->with('tiktokUser')
                        ->where('is_uploaded', false) // 업로드 안된 것만
                        ->where(function($q) {
                            $q->whereNull('deadline_date')
                              ->orWhere('deadline_date', '>', now()); // 기한 안 지난 것만
                        })
                        ->get()
                        ->pluck('tiktokUser.username')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    if (empty($usernames)) {
                        Notification::make()
                            ->warning()
                            ->title('사용자 정보 없음')
                            ->body('현재 필터 조건에 맞는 유효한 사용자가 없습니다.')
                            ->send();
                        return;
                    }

                    try {
                        $apiUrl = rtrim(config('app.api_url'), '/') . '/api/v1/tiktok/scrape_video';
                        
                        Http::post($apiUrl, [
                            'usernames' => $usernames
                        ]);

                        Notification::make()
                            ->success()
                            ->title('비디오 스크랩 요청 완료')
                            ->body(count($usernames) . '명의 사용자에 대한 비디오 스크랩이 백그라운드에서 진행됩니다.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('오류 발생')
                            ->body('비디오 스크랩 중 오류가 발생했습니다: ' . $e->getMessage())
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
