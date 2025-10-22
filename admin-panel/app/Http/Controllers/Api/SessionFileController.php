<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TiktokSender;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SessionFileController extends Controller
{
    /**
     * 세션 파일 다운로드 API
     * 보안: API 토큰 또는 IP 제한을 통한 접근 제어
     */
    public function download(Request $request, $senderId)
    {
        try {
            // TikTok 발신자 정보 조회
            $sender = TiktokSender::findOrFail($senderId);
            
            // 세션 파일 경로 확인
            if (!$sender->session_file_path || !file_exists($sender->session_file_path)) {
                return response()->json([
                    'error' => 'Session file not found',
                    'message' => 'No session file exists for this sender'
                ], 404);
            }

            // 보안 체크: 허용된 IP에서만 접근 가능
            $allowedIps = config('app.api_allowed_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'IP address not allowed'
                ], 403);
            }

            // 파일이 public 디렉토리 내에 있는지 확인 (보안)
            $publicPath = storage_path('app/public');
            $realSessionPath = realpath($sender->session_file_path);
            $realPublicPath = realpath($publicPath);

            if (!$realSessionPath || !str_starts_with($realSessionPath, $realPublicPath)) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'File path not allowed'
                ], 403);
            }

            // 파일 다운로드 응답
            return response()->file($sender->session_file_path, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="session_' . $senderId . '.json"',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Download failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 세션 파일 정보 조회
     */
    public function info($senderId)
    {
        try {
            $sender = TiktokSender::findOrFail($senderId);
            
            return response()->json([
                'sender_id' => $senderId,
                'nickname' => $sender->nickname,
                'session_exists' => !empty($sender->session_file_path) && file_exists($sender->session_file_path),
                'session_updated_at' => $sender->session_updated_at,
                'file_size' => $sender->session_file_path && file_exists($sender->session_file_path) 
                    ? filesize($sender->session_file_path) 
                    : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Info failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}