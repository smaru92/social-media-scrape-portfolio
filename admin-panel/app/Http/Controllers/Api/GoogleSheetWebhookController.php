<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TiktokUser;
use App\Models\TiktokUserPersonalInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoogleSheetWebhookController extends Controller
{
    public function receivePersonalInfo(Request $request)
    {
        try {
            // 요청 데이터 로깅
            Log::info('Google Sheet Webhook received', $request->all());

            // 데이터 검증
            $validated = $request->validate([
                'created_at' => 'required|string',
                'name' => 'required|string',
                'sns_username' => 'required|string',
                'sns_url' => 'nullable|string',
                'address' => 'required|string',
                'zipcode' => 'required|string',
                'phone' => 'required|string',
                'repost_permission' => 'nullable|string',
                'brand_feedback' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // TiktokUser 찾기 (username 또는 instagram_url로)
            $tiktokUser = TiktokUser::where('username', $validated['sns_username'])
                ->orWhere('profile_url', 'like', '%' . $validated['sns_username'] . '%')
                ->first();

            if (!$tiktokUser) {
                // 사용자가 없으면 새로 생성
                $tiktokUser = TiktokUser::create([
                    'username' => $validated['sns_username'],
                    'profile_url' => $validated['sns_url'] ?? null,
                    'created_at' => now(),
                ]);

                Log::info('New TiktokUser created', ['id' => $tiktokUser->id, 'username' => $tiktokUser->username]);
            }

            // repost_permission 값 변환
            $repostPermission = 'P';
            if (!empty($validated['repost_permission'])) {
                $permission = mb_strtolower(trim($validated['repost_permission']));
                if (in_array($permission, ['yes', 'はい', 'ok', '허가', '예'])) {
                    $repostPermission = 'Y';
                } elseif (in_array($permission, ['no', 'いいえ', 'ng', '불허', '아니오'])) {
                    $repostPermission = 'N';
                }
            }

            // 폼 제출 시간 파싱
            $formSubmittedAt = null;
            try {
                $formSubmittedAt = \Carbon\Carbon::parse($validated['created_at']);
            } catch (\Exception $e) {
                Log::warning('Failed to parse created_at', ['value' => $validated['created_at']]);
                $formSubmittedAt = now();
            }

            // PersonalInfo 생성 또는 업데이트
            $personalInfo = TiktokUserPersonalInfo::updateOrCreate(
                ['tiktok_user_id' => $tiktokUser->id],
                [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'postal_code' => $validated['zipcode'],
                    'category1' => $validated['category1'],
                    'category2' => $validated['category2'],
                    'brand_feedback' => $validated['brand_feedback'] ?? null,
                    'repost_permission' => $repostPermission,
                    'created_at' => $formSubmittedAt,
                ]
            );

            // TiktokUser의 협업 상태 업데이트
            $tiktokUser->update([
                'is_collaborator' => 1,
                'collaborated_at' => now(),
            ]);

            DB::commit();

            Log::info('PersonalInfo saved successfully', [
                'user_id' => $tiktokUser->id,
                'personal_info_id' => $personalInfo->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Personal information saved successfully',
                'data' => [
                    'user_id' => $tiktokUser->id,
                    'personal_info_id' => $personalInfo->id,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Google Sheet Webhook Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save personal information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
