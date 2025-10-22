<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TiktokMessage;
use App\Models\TiktokMessageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendScheduledTiktokMessages extends Command
{
    protected $signature = 'tiktok:send-scheduled-messages';
    protected $description = 'Send scheduled TikTok messages';

    public function handle()
    {
        $now = Carbon::now();

        Log::channel('tiktok')->info('=== TikTok 스케줄 메시지 발송 시작 ===', [
            'started_at' => $now->toDateTimeString()
        ]);

        // 자동 발송이 활성화되고 시간이 된 메시지 조회
        $messages = TiktokMessage::where('is_auto', true)
            ->where('is_complete', false)
            ->where('start_at', '<=', $now)
            ->where(function($query) use ($now) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->get();

        Log::channel('tiktok')->info('발송 대상 메시지 조회 완료', [
            'total_messages' => $messages->count(),
            'message_ids' => $messages->pluck('id')->toArray()
        ]);

        foreach ($messages as $message) {
            try {
                Log::channel('tiktok')->info('메시지 처리 시작', [
                    'message_id' => $message->id,
                    'template_id' => $message->tiktok_message_template_id
                ]);
                // 발신 계정의 세션 파일 경로 가져오기
                $sender = $message->tiktok_sender;
                $sessionFilePath = $sender->session_file_path ?? null;

                // 템플릿 정보 가져오기
                $template = $message->tiktok_message_template;

                if (!$template) {
                    Log::channel('tiktok')->warning("Message template not found for message ID {$message->id}");
                    continue;
                }

                // 사용자 목록 가져오기
                $usernames = $message->tiktok_users()->pluck('username')->filter()->toArray();
                if (empty($usernames)) {
                    $usernames = $message->tiktok_users()->pluck('nickname')->filter()->toArray();
                }

                // 수신자가 없으면 건너뛰기
                if (empty($usernames)) {
                    Log::channel('tiktok')->info("No recipients for message ID {$message->id}");
                    continue;
                }

                Log::channel('tiktok')->info('수신자 목록 조회', [
                    'message_id' => $message->id,
                    'total_recipients' => count($usernames)
                ]);

                // 이미 발송된 사용자 제외
                $sendUsernames = TiktokMessageLog::where('tiktok_message_id', $message->id)
                    ->where('result', '전송성공')
                    ->join('tiktok_users', 'tiktok_message_logs.tiktok_user_id', '=', 'tiktok_users.id')
                    ->pluck('tiktok_users.username')
                    ->toArray();

                $usernamesToSend = array_diff($usernames, $sendUsernames);

                if (empty($usernamesToSend)) {
                    Log::channel('tiktok')->info("All users already received message ID {$message->id}");

                    // 모든 사용자에게 발송 완료시 메시지 완료 처리
                    $message->update(['is_complete' => true]);
                    Log::channel('tiktok')->info('메시지 완료 처리', ['message_id' => $message->id]);
                    continue;
                }

                Log::channel('tiktok')->info('발송 대상 사용자 필터링 완료', [
                    'message_id' => $message->id,
                    'already_send' => count($sendUsernames),
                    'to_send' => count($usernamesToSend)
                ]);

                // API 호출 데이터 구성 (Python FastAPI 스키마에 맞춤)
                $apiData = [
                    'usernames' => array_values($usernamesToSend),
                    'template_code' => $template->template_code,
                    'session_file_path' => $sessionFilePath,
                    'message_id' => $message->id,
                ];

                Log::channel('tiktok')->info('API 호출 준비', [
                    'message_id' => $message->id,
                    'api_url' => config('app.api_url') . '/api/v1/tiktok/send_message',
                    'template_code' => $template->template_code,
                    'usernames_count' => count($usernamesToSend)
                ]);

                // API 호출
                $apiUrl = config('app.api_url') . '/api/v1/tiktok/send_message';

                $response = Http::timeout(15)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($apiUrl, $apiData);

                // API 응답 로깅
                Log::channel('tiktok')->info('API 응답 수신', [
                    'message_id' => $message->id,
                    'http_status' => $response->status(),
                    'success' => $response->successful(),
                    'usernames_count' => count($usernamesToSend),
                    'response_body' => substr($response->body(), 0, 500)
                ]);

                if ($response->successful()) {
                    $this->info("Message ID {$message->id} send to " . count($usernamesToSend) . " users");

                    Log::channel('tiktok')->info('메시지 발송 성공', [
                        'message_id' => $message->id,
                        'recipients_count' => count($usernamesToSend)
                    ]);

                    // 발송 로그 기록
                    $loggedCount = 0;
                    $responseData = $response->json();
                    $sendTime = Carbon::now()->toDateTimeString();
                    foreach ($usernamesToSend as $username) {
                        $user = $message->tiktok_users()->where('username', $username)->first();
                        if ($user) {
                            TiktokMessageLog::create([
                                'tiktok_message_id' => $message->id,
                                'tiktok_user_id' => $user->id,
                                'tiktok_sender_id' => $sender->id ?? null,
                                'message_text' => $template->content ?? '',
                                'result' => '전송성공',
                                'result_text' => json_encode([
                                    'send_time' => $sendTime,
                                    'response' => $responseData
                                ], JSON_UNESCAPED_UNICODE)
                            ]);
                            $loggedCount++;
                        }
                    }

                    Log::channel('tiktok')->info('발송 로그 기록 완료', [
                        'message_id' => $message->id,
                        'logged_count' => $loggedCount
                    ]);
                } else {
                    $this->error("Failed to send message ID {$message->id}: HTTP {$response->status()}");
                    Log::channel('tiktok')->error('메시지 발송 실패', [
                        'message_id' => $message->id,
                        'http_status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    
                    // 실패 로그 기록
                    $responseData = $response->json() ?? ['error' => $response->body()];
                    $sendTime = Carbon::now()->toDateTimeString();
                    foreach ($usernamesToSend as $username) {
                        $user = $message->tiktok_users()->where('username', $username)->first();
                        if ($user) {
                            TiktokMessageLog::create([
                                'tiktok_message_id' => $message->id,
                                'tiktok_user_id' => $user->id,
                                'tiktok_sender_id' => $sender->id ?? null,
                                'message_text' => $template->content ?? '',
                                'result' => '전송실패',
                                'result_text' => json_encode([
                                    'send_time' => $sendTime,
                                    'http_status' => $response->status(),
                                    'response' => $responseData
                                ], JSON_UNESCAPED_UNICODE)
                            ]);
                        }
                    }
                }

            } catch (\Exception $e) {
                Log::channel('tiktok')->error("메시지 처리 중 오류 발생", [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $this->error("Error processing message ID {$message->id}: " . $e->getMessage());
            }
        }

        Log::channel('tiktok')->info('=== TikTok 스케줄 메시지 발송 완료 ===', [
            'completed_at' => Carbon::now()->toDateTimeString(),
            'processed_messages' => $messages->count()
        ]);

        $this->info('Scheduled messages processing completed');
        return Command::SUCCESS;
    }
}
