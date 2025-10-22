<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\TiktokUser;
use App\Models\TiktokVideo;
use App\Models\TikTokRepostVideo;
use Illuminate\Support\Facades\Http;

class TikTokImageController extends Controller
{
    /**
     * 이미지 업로드 API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_type' => 'required|in:user,video,repost_video',
            'tiktok_username' => 'required|string',
            'image' => 'required|file|image|max:10240', // 10MB max
            'record_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tableType = $request->table_type;
            $tikTokUsername = $request->tiktok_username;
            $recordId = $request->record_id;
            $image = $request->file('image');

            // 파일명 생성
            $extension = $image->getClientOriginalExtension();
            $fileName = uniqid() . '_' . time() . '.' . $extension;

            // 저장 경로: tiktok_images/{tiktok_username}/{file_name}
            $relativePath = "tiktok_images/{$tikTokUsername}/{$fileName}";

            // public 디스크에 파일 저장
            $storedPath = $image->storeAs("tiktok_images/{$tikTokUsername}", $fileName, 'public');

            if (!$storedPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store image'
                ], 500);
            }

            // 데이터베이스 업데이트
            $updateResult = $this->updateImagePath($tableType, $recordId, $relativePath);

            if (!$updateResult) {
                // 파일 저장은 성공했지만 DB 업데이트 실패시 파일 삭제
                Storage::disk('public')->delete($storedPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update database'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_path' => $relativePath,
                    'table_type' => $tableType,
                    'record_id' => $recordId,
                    'tiktok_username' => $tikTokUsername
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 테이블 타입에 따라 이미지 경로 업데이트
     *
     * @param string $tableType
     * @param int $recordId
     * @param string $imagePath
     * @return bool
     */
    private function updateImagePath(string $tableType, int $recordId, string $imagePath): bool
    {
        try {
            switch ($tableType) {
                case 'user':
                    $record = TiktokUser::find($recordId);
                    if ($record) {
                        $record->profile_image = $imagePath;
                        return $record->save();
                    }
                    break;

                case 'video':
                    $record = TiktokVideo::find($recordId);
                    if ($record) {
                        $record->thumbnail_url = $imagePath;
                        return $record->save();
                    }
                    break;

                case 'repost_video':
                    $record = TikTokRepostVideo::find($recordId);
                    if ($record) {
                        $record->thumbnail_url = $imagePath;
                        return $record->save();
                    }
                    break;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Failed to update image path: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 리포스트 사용자 수집 콜백 API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function callbackCollectRepostUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->input('limit', 100);

            // /collect-repost-users API 호출
            $apiUrl = config('app.api_url') . '/api/v1/tiktok/collect-repost-users';
            $response = Http::timeout(60)->post($apiUrl, [
                'limit' => $limit
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully called collect-repost-users API',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to call collect-repost-users API',
                    'status' => $response->status(),
                    'response' => $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to call collect-repost-users API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to call API: ' . $e->getMessage()
            ], 500);
        }
    }
}
