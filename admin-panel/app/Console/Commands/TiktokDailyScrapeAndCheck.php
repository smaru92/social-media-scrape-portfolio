<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TiktokUploadRequest;
use App\Models\TiktokUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiktokDailyScrapeAndCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:daily-scrape {--check-only : Only run upload check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '매일 실행되는 틱톡 비디오 스크랩 및 업로드 체크 작업';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('check-only')) {
            $this->checkUploads();
            return;
        }

        // 1. 비디오 스크랩 실행
        $this->scrapeVideos();
        
        // 2. 2시간 후 업로드 체크를 위한 스케줄 등록
        $this->info('비디오 스크랩 완료. 2시간 후 업로드 체크가 실행됩니다.');
    }

    /**
     * 업로드되지 않은 요청에 대한 비디오 스크랩
     */
    protected function scrapeVideos()
    {
        $this->info('비디오 스크랩 시작...');
        
        try {
            // 업로드되지 않은 요청 조회
            $requests = TiktokUploadRequest::where('is_uploaded', false)
                ->where('is_confirm', false)
                ->whereNotNull('deadline_date')
                ->where('deadline_date', '>=', now())
                ->with('tiktokUser')
                ->get();

            if ($requests->isEmpty()) {
                $this->info('스크랩할 업로드 요청이 없습니다.');
                return;
            }

            $this->info($requests->count() . '개의 업로드 요청에 대해 스크랩을 시작합니다.');

            // 사용자별로 그룹화
            $userIds = $requests->pluck('tiktok_user_id')->unique();
            
            foreach ($userIds as $userId) {
                $user = TiktokUser::find($userId);
                if (!$user || !$user->username) {
                    continue;
                }

                $this->info("스크랩 중: @{$user->username}");

                try {
                    // API 호출
                    $apiUrl = config('app.api_url', 'http://localhost:8000');
                    $response = Http::timeout(30)->post("{$apiUrl}/api/v1/tiktok/scrape_video", [
                        'username' => $user->username,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        if (isset($data['status']) && $data['status'] === 'success') {
                            $this->info("✓ @{$user->username} 스크랩 성공");
                            Log::info('TikTok 비디오 스크랩 성공', [
                                'username' => $user->username,
                                'video_count' => $data['video_count'] ?? 0
                            ]);
                        } else {
                            $this->error("✗ @{$user->username} 스크랩 실패: " . ($data['message'] ?? 'Unknown error'));
                            Log::error('TikTok 비디오 스크랩 실패', [
                                'username' => $user->username,
                                'error' => $data['message'] ?? 'Unknown error'
                            ]);
                        }
                    } else {
                        $this->error("✗ @{$user->username} API 호출 실패");
                        Log::error('TikTok 비디오 스크랩 API 호출 실패', [
                            'username' => $user->username,
                            'status' => $response->status()
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->error("✗ @{$user->username} 스크랩 중 오류: " . $e->getMessage());
                    Log::error('TikTok 비디오 스크랩 예외', [
                        'username' => $user->username,
                        'error' => $e->getMessage()
                    ]);
                }

                // API 과부하 방지를 위한 지연
                sleep(2);
            }

            $this->info('비디오 스크랩 완료!');
            
        } catch (\Exception $e) {
            $this->error('비디오 스크랩 중 오류 발생: ' . $e->getMessage());
            Log::error('TikTok 일일 스크랩 오류', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 업로드 체크 실행
     */
    protected function checkUploads()
    {
        $this->info('업로드 체크 시작...');
        
        try {
            // 업로드되지 않은 요청 조회
            $requests = TiktokUploadRequest::where('is_uploaded', false)
                ->where('is_confirm', false)
                ->whereNotNull('deadline_date')
                ->with('tiktokUser')
                ->get();

            if ($requests->isEmpty()) {
                $this->info('체크할 업로드 요청이 없습니다.');
                return;
            }

            $this->info($requests->count() . '개의 업로드 요청을 체크합니다.');

            foreach ($requests as $request) {
                if (!$request->tiktokUser || !$request->tiktokUser->username) {
                    continue;
                }

                $username = $request->tiktokUser->username;
                $this->info("체크 중: @{$username} (요청 ID: {$request->id})");

                try {
                    // API 호출하여 업로드 상태 확인
                    $apiUrl = config('app.api_url', 'http://localhost:8000');
                    $response = Http::timeout(30)->post("{$apiUrl}/api/v1/tiktok/check_upload", [
                        'upload_request_id' => $request->id,
                        'username' => $username,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        if (isset($data['is_uploaded']) && $data['is_uploaded'] === true) {
                            // 업로드 완료 상태로 업데이트
                            $request->update([
                                'is_uploaded' => true,
                                'uploaded_at' => now(),
                                'video_url' => $data['video_url'] ?? null,
                            ]);
                            
                            $this->info("✓ @{$username} 업로드 확인됨");
                            Log::info('TikTok 업로드 확인', [
                                'request_id' => $request->id,
                                'username' => $username,
                                'video_url' => $data['video_url'] ?? null
                            ]);
                        } else {
                            $this->info("- @{$username} 아직 업로드되지 않음");
                        }
                    } else {
                        $this->error("✗ @{$username} 체크 실패");
                        Log::error('TikTok 업로드 체크 실패', [
                            'request_id' => $request->id,
                            'username' => $username,
                            'status' => $response->status()
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->error("✗ @{$username} 체크 중 오류: " . $e->getMessage());
                    Log::error('TikTok 업로드 체크 예외', [
                        'request_id' => $request->id,
                        'username' => $username,
                        'error' => $e->getMessage()
                    ]);
                }

                // API 과부하 방지를 위한 지연
                sleep(1);
            }

            $this->info('업로드 체크 완료!');
            
        } catch (\Exception $e) {
            $this->error('업로드 체크 중 오류 발생: ' . $e->getMessage());
            Log::error('TikTok 업로드 체크 오류', ['error' => $e->getMessage()]);
        }
    }
}