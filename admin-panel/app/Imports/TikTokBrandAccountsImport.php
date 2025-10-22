<?php

namespace App\Imports;

use App\Models\TikTokBrandAccount;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TikTokBrandAccountsImport
{
    protected $errors = [];
    protected $successCount = 0;

    public function import($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // 첫 번째 행은 헤더
        $headers = array_shift($rows);

        // 헤더 인덱스 찾기
        $usernameIndex = array_search('계정명', $headers);
        $brandNameIndex = array_search('브랜드명', $headers);
        $countryIndex = array_search('국가', $headers);
        $categoryIndex = array_search('카테고리', $headers);
        $profileUrlIndex = array_search('프로필_URL', $headers);

        if ($usernameIndex === false) {
            throw new \Exception('엑셀 파일에 "계정명" 컬럼이 없습니다.');
        }

        foreach ($rows as $rowNumber => $row) {
            // 빈 행 건너뛰기
            if (empty($row[$usernameIndex])) {
                continue;
            }

            $data = [
                '계정명' => $row[$usernameIndex] ?? null,
                '브랜드명' => ($brandNameIndex !== false) ? $row[$brandNameIndex] : null,
                '국가' => ($countryIndex !== false) ? $row[$countryIndex] : null,
                '카테고리' => ($categoryIndex !== false) ? $row[$categoryIndex] : null,
                '프로필_URL' => ($profileUrlIndex !== false) ? $row[$profileUrlIndex] : null,
            ];

            // 유효성 검사
            $validator = Validator::make($data, [
                '계정명' => 'required|string|max:255',
                '브랜드명' => 'nullable|string|max:255',
                '국가' => 'nullable|string|max:2',
                '카테고리' => 'nullable|string|max:255',
                '프로필_URL' => 'nullable|url|max:500',
            ], [
                '계정명.required' => '계정명은 필수 입력 항목입니다.',
                '계정명.string' => '계정명은 문자열이어야 합니다.',
                '계정명.max' => '계정명은 255자를 초과할 수 없습니다.',
                '브랜드명.string' => '브랜드명은 문자열이어야 합니다.',
                '브랜드명.max' => '브랜드명은 255자를 초과할 수 없습니다.',
                '국가.string' => '국가는 문자열이어야 합니다.',
                '국가.max' => '국가 코드는 2자리여야 합니다.',
                '카테고리.string' => '카테고리는 문자열이어야 합니다.',
                '카테고리.max' => '카테고리는 255자를 초과할 수 없습니다.',
                '프로필_URL.url' => '프로필 URL은 유효한 URL 형식이어야 합니다.',
                '프로필_URL.max' => '프로필 URL은 500자를 초과할 수 없습니다.',
            ]);

            if ($validator->fails()) {
                $this->errors[] = "행 " . ($rowNumber + 2) . ": " . implode(', ', $validator->errors()->all());
                continue;
            }

            // 데이터 저장
            TikTokBrandAccount::updateOrCreate(
                ['username' => $data['계정명']],
                [
                    'brand_name' => $data['브랜드명'],
                    'country' => $data['국가'],
                    'category' => $data['카테고리'],
                    'profile_url' => $data['프로필_URL'],
                    'status' => 'active',
                ]
            );

            $this->successCount++;
        }

        if (!empty($this->errors)) {
            throw ValidationException::withMessages(['import' => $this->errors]);
        }

        return $this->successCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }
}