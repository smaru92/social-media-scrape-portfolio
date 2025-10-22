<?php

namespace App\Console\Commands;

use App\Models\TiktokAutoDmConfig;
use App\Models\TiktokUser;
use App\Models\TiktokMessage;
use App\Models\TiktokMessageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class SendAutoDm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:send-auto-dm
                            {--config-id= : 특정 설정 ID만 실행}
                            {--force : 스케줄 무시하고 강제 실행}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '자동 DM 설정에 따라 승인되고 미확인 상태인 사용자에게 DM을 발송합니다 (하루 최대 100건)';

    /**
     * 하루 최대 발송 건수
     */
    const DAILY_LIMIT = 100;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 로그 디렉토리 생성
        $logDir = storage_path('logs/tiktok');
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        Log::channel('tiktok_auto_dm')->info('========================================');
        Log::channel('tiktok_auto_dm')->info('자동 DM 발송 시작', [
            'timestamp' => now()->toDateTimeString(),
            'command_options' => [
                'config_id' => $this->option('config-id'),
                'force' => $this->option('force'),
            ]
        ]);

        $this->info('자동 DM 발송 시작...');

        // 오늘 발송한 건수 확인
        $todaySentCount = TiktokMessageLog::whereDate('created_at', today())
            ->where('result', 'success')
            ->count();

        $remainingLimit = self::DAILY_LIMIT - $todaySentCount;

        Log::channel('tiktok_auto_dm')->info('오늘 발송 현황', [
            'sent_count' => $todaySentCount,
            'daily_limit' => self::DAILY_LIMIT,
            'remaining' => $remainingLimit,
        ]);

        $this->info("오늘 발송 현황: {$todaySentCount}건 / " . self::DAILY_LIMIT . "건");
        $this->info("남은 발송 가능 건수: {$remainingLimit}건");

        if ($remainingLimit <= 0) {
            Log::channel('tiktok_auto_dm')->warning('오늘의 발송 한도 초과', [
                'sent_count' => $todaySentCount,
                'daily_limit' => self::DAILY_LIMIT,
            ]);
            $this->warn('오늘의 발송 한도를 초과했습니다.');
            return Command::SUCCESS;
        }

        // 활성화된 설정 가져오기
        $configs = TiktokAutoDmConfig::where('is_active', true);

        if ($this->option('config-id')) {
            $configs->where('id', $this->option('config-id'));
        }

        $configs = $configs->with(['sender', 'messageTemplate'])->get();

        if ($configs->isEmpty()) {
            Log::channel('tiktok_auto_dm')->warning('활성화된 자동 DM 설정 없음');
            $this->warn('활성화된 자동 DM 설정이 없습니다.');
            return Command::SUCCESS;
        }

        Log::channel('tiktok_auto_dm')->info('활성화된 설정 확인', [
            'config_count' => $configs->count(),
            'configs' => $configs->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'country' => $c->country,
            ])->toArray(),
        ]);

        $this->info("총 {$configs->count()}개의 설정을 확인합니다.");

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($configs as $config) {
            // 남은 발송 가능 건수 재확인
            if ($remainingLimit <= 0) {
                Log::channel('tiktok_auto_dm')->warning('발송 한도 도달로 중단', [
                    'remaining_configs' => $configs->count() - $configs->search($config),
                ]);
                $this->warn("\n오늘의 발송 한도를 모두 사용했습니다. 남은 설정은 건너뜁니다.");
                break;
            }

            Log::channel('tiktok_auto_dm')->info("설정 처리 시작: {$config->name}", [
                'config_id' => $config->id,
                'config_name' => $config->name,
                'country' => $config->country,
                'min_review_score' => $config->min_review_score,
            ]);

            $this->line("\n[{$config->name}] 처리 시작...");

            // 국가 설정 확인
            if (empty($config->country)) {
                Log::channel('tiktok_auto_dm')->error('국가 미설정', ['config_id' => $config->id]);
                $this->error("  국가가 설정되지 않았습니다.");
                continue;
            }

            $this->line("  대상 국가: {$config->country}");

            // 발송 계정과 템플릿 확인
            if (!$config->sender) {
                Log::channel('tiktok_auto_dm')->error('발신 계정 미설정', ['config_id' => $config->id]);
                $this->error("  발신 계정이 설정되지 않았습니다.");
                continue;
            }

            if (!$config->messageTemplate) {
                Log::channel('tiktok_auto_dm')->error('메시지 템플릿 미설정', ['config_id' => $config->id]);
                $this->error("  메시지 템플릿이 설정되지 않았습니다.");
                continue;
            }

            // 스케줄 확인 (--force 옵션이 없을 때만)
            if (!$this->option('force') && !$this->shouldRun($config)) {
                Log::channel('tiktok_auto_dm')->info('스케줄 조건 불일치로 건너뜀', [
                    'config_id' => $config->id,
                    'schedule_type' => $config->schedule_type,
                    'schedule_time' => $config->schedule_time,
                ]);
                $this->line("  스케줄 조건에 맞지 않아 건너뜁니다.");
                continue;
            }

            // 대상 사용자 조회: 승인된 사용자 중 미확인 상태이며 최소 점수 이상 + 국가 일치
            $targetUsers = TiktokUser::where('review_status', TiktokUser::REVIEW_STATUS_APPROVED)
                ->where('status', TiktokUser::STATUS_UNCONFIRMED)
                ->where('country', $config->country)
                ->where('review_score', '>=', $config->min_review_score)
                ->whereNotNull('username')
                ->limit($remainingLimit) // 남은 발송 가능 건수만큼만 조회
                ->get();

            if ($targetUsers->isEmpty()) {
                Log::channel('tiktok_auto_dm')->warning('발송 대상 사용자 없음', [
                    'config_id' => $config->id,
                    'country' => $config->country,
                    'min_review_score' => $config->min_review_score,
                ]);
                $this->warn("  발송 대상 사용자가 없습니다.");
                continue;
            }

            Log::channel('tiktok_auto_dm')->info('발송 대상 사용자 조회 완료', [
                'config_id' => $config->id,
                'target_count' => $targetUsers->count(),
                'remaining_limit' => $remainingLimit,
                'users' => $targetUsers->pluck('username')->toArray(),
            ]);

            $this->info("  대상 사용자: {$targetUsers->count()}명 (최대 {$remainingLimit}명)");

            // TiktokMessage 생성
            $tiktokMessage = TiktokMessage::create([
                'tiktok_sender_id' => $config->sender->id,
                'tiktok_message_template_id' => $config->messageTemplate->id,
                'title' => "[자동발송] {$config->name} - " . now()->format('Y-m-d H:i'),
                'is_auto' => true,
                'is_complete' => false,
                'start_at' => now(),
            ]);

            Log::channel('tiktok_auto_dm')->info('TiktokMessage 생성', [
                'tiktok_message_id' => $tiktokMessage->id,
                'title' => $tiktokMessage->title,
            ]);

            // username 배열 준비
            $usernames = $targetUsers->pluck('username')->toArray();

            // 사용자 ID 매핑 (나중에 로그 기록용)
            $userMap = $targetUsers->keyBy('username');

            $this->line("  배치 발송 시작: " . implode(', ', $usernames));

            // 배치로 DM 발송
            try {
                $result = $this->sendDmBatch($config, $usernames, $tiktokMessage->id);

                $sent = $result['sent'] ?? 0;
                $failed = $result['failed'] ?? 0;

                // 각 사용자별 로그 생성
                foreach ($targetUsers as $user) {
                    TiktokMessageLog::create([
                        'tiktok_user_id' => $user->id,
                        'tiktok_message_id' => $tiktokMessage->id,
                        'message_text' => $result['message_text'] ?? '',
                        'tiktok_sender_id' => $config->sender->id,
                        'result' => 'success', // 배치 발송이므로 API 성공으로 간주
                        'result_text' => $result['message'] ?? 'DM 발송 요청 완료',
                    ]);
                }

                // 남은 발송 건수 업데이트 (성공한 건수만큼 감소)
                $remainingLimit -= $sent;

                Log::channel('tiktok_auto_dm')->info('배치 DM 발송 완료', [
                    'config_id' => $config->id,
                    'sent' => $sent,
                    'failed' => $failed,
                    'remaining_limit' => $remainingLimit,
                ]);

                $this->info("  ✓ 배치 발송 완료: 성공 {$sent}건, 실패 {$failed}건 (남은 발송 가능: {$remainingLimit}건)");

            } catch (\Exception $e) {
                $failed = $targetUsers->count();

                Log::channel('tiktok_auto_dm')->error('배치 DM 발송 예외 발생', [
                    'config_id' => $config->id,
                    'config_name' => $config->name,
                    'target_count' => $targetUsers->count(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("  ✗ 배치 발송 실패: {$e->getMessage()}");

                // 실패한 사용자들에 대해 로그 기록
                foreach ($targetUsers as $user) {
                    TiktokMessageLog::create([
                        'tiktok_user_id' => $user->id,
                        'tiktok_message_id' => $tiktokMessage->id,
                        'message_text' => '',
                        'tiktok_sender_id' => $config->sender->id,
                        'result' => 'error',
                        'result_text' => $e->getMessage(),
                    ]);
                }
            }

            // 메시지 완료 처리
            $tiktokMessage->update([
                'is_complete' => true,
                'end_at' => now(),
            ]);

            Log::channel('tiktok_auto_dm')->info('TiktokMessage 완료 처리', [
                'tiktok_message_id' => $tiktokMessage->id,
                'start_at' => $tiktokMessage->start_at,
                'end_at' => now(),
            ]);

            // 설정 업데이트: 마지막 발송 시간
            $config->update([
                'last_sent_at' => now(),
            ]);

            $totalSent += $sent ?? 0;
            $totalFailed += $failed ?? 0;

            Log::channel('tiktok_auto_dm')->info("설정 처리 완료: {$config->name}", [
                'config_id' => $config->id,
                'sent' => $sent,
                'failed' => $failed,
                'total' => $sent + $failed,
            ]);

            $this->info("  완료: 성공 {$sent}건, 실패 {$failed}건");
        }

        $this->newLine();
        $this->info("전체 완료: 성공 {$totalSent}건, 실패 {$totalFailed}건");

        Log::channel('tiktok_auto_dm')->info('자동 DM 발송 종료', [
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'total' => $totalSent + $totalFailed,
            'remaining_limit' => $remainingLimit,
            'timestamp' => now()->toDateTimeString(),
        ]);
        Log::channel('tiktok_auto_dm')->info('========================================');

        return Command::SUCCESS;
    }

    /**
     * 스케줄에 따라 지금 실행해야 하는지 확인
     */
    protected function shouldRun(TiktokAutoDmConfig $config): bool
    {
        $now = Carbon::now();
        $scheduleTime = Carbon::parse($config->schedule_time);

        // 현재 시간이 설정된 시간과 같은지 확인 (분 단위)
        if ($now->format('H:i') !== $scheduleTime->format('H:i')) {
            return false;
        }

        // 발송 주기에 따라 추가 확인
        switch ($config->schedule_type) {
            case TiktokAutoDmConfig::SCHEDULE_DAILY:
                // 매일 발송: 항상 true
                return true;

            case TiktokAutoDmConfig::SCHEDULE_WEEKLY:
                // 매주 특정 요일: 현재 요일 확인 (0=일요일, 6=토요일)
                return $now->dayOfWeek == $config->schedule_day;

            case TiktokAutoDmConfig::SCHEDULE_MONTHLY:
                // 매월 특정 일: 현재 일자 확인
                return $now->day == $config->schedule_day;

            default:
                return false;
        }
    }

    /**
     * 배치 DM 발송 (API 호출)
     */
    protected function sendDmBatch(TiktokAutoDmConfig $config, array $usernames, int $messageId): array
    {
        try {
            $apiUrl = config('app.api_url') . '/api/v1/tiktok/send_message';

            // 세션 파일 경로
            $sessionFilePath = $config->sender->session_file_path ?? null;

            // API 호출 데이터 구성
            $apiData = [
                'usernames' => $usernames,
                'template_code' => $config->messageTemplate->template_code,
                'session_file_path' => $sessionFilePath,
                'message_id' => $messageId,
            ];

            Log::channel('tiktok_auto_dm')->info('배치 API 호출 시작', [
                'api_url' => $apiUrl,
                'sender_id' => $config->sender->id,
                'usernames_count' => count($usernames),
                'usernames' => $usernames,
                'template_code' => $config->messageTemplate->template_code,
            ]);

            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl, $apiData);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('tiktok_auto_dm')->info('배치 API 응답 성공', [
                    'status_code' => $response->status(),
                    'response_data' => $data,
                ]);

                return [
                    'sent' => count($usernames),
                    'failed' => 0,
                    'message' => $data['message'] ?? 'DM 배치 발송 성공',
                    'message_text' => $this->buildMessageText($config->messageTemplate),
                ];
            } else {
                Log::channel('tiktok_auto_dm')->error('배치 API 응답 오류', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                throw new \Exception('API 응답 오류: ' . $response->status() . ' - ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::channel('tiktok_auto_dm')->error('배치 API 호출 예외', [
                'usernames' => $usernames,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 메시지 템플릿에서 메시지 텍스트 생성
     */
    protected function buildMessageText($template): string
    {
        $parts = [];

        if (!empty($template->message_header_json)) {
            $headers = is_array($template->message_header_json)
                ? $template->message_header_json
                : json_decode($template->message_header_json, true);

            if (is_array($headers)) {
                // 배열 내부에 또 배열이 있는 경우 처리
                $filteredHeaders = array_filter($headers, fn($item) => !is_array($item) && !empty($item));
                if (!empty($filteredHeaders)) {
                    $parts[] = implode("\n", $filteredHeaders);
                }
            }
        }

        if (!empty($template->message_body_json)) {
            $bodies = is_array($template->message_body_json)
                ? $template->message_body_json
                : json_decode($template->message_body_json, true);

            if (is_array($bodies)) {
                // 배열 내부에 또 배열이 있는 경우 처리
                $filteredBodies = array_filter($bodies, fn($item) => !is_array($item) && !empty($item));
                if (!empty($filteredBodies)) {
                    $parts[] = implode("\n", $filteredBodies);
                }
            }
        }

        if (!empty($template->message_footer_json)) {
            $footers = is_array($template->message_footer_json)
                ? $template->message_footer_json
                : json_decode($template->message_footer_json, true);

            if (is_array($footers)) {
                // 배열 내부에 또 배열이 있는 경우 처리
                $filteredFooters = array_filter($footers, fn($item) => !is_array($item) && !empty($item));
                if (!empty($filteredFooters)) {
                    $parts[] = implode("\n", $filteredFooters);
                }
            }
        }

        return implode("\n\n", array_filter($parts));
    }
}
