<?php

namespace App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\Pages;

use App\Filament\Admin\Resources\TiktokUserPersonalInfoResource;
use App\Models\TiktokUploadRequest;
use App\Models\TiktokUserPersonalInfo;
use App\Models\TiktokUser;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListTiktokUserPersonalInfos extends ListRecords
{
    protected static string $resource = TiktokUserPersonalInfoResource::class;

    public function getTableDescription(): ?string
    {
        return '틱톡 사용자의 개인정보가 기록됩니다. (구글폼과 연동시 연동데이터가 자동으로 기록됩니다.)';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importExcel')
                ->label('엑셀 일괄 등록')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->form([
                    FileUpload::make('file')
                        ->label('엑셀 파일')
                        ->required()
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->helperText('엑셀 파일을 업로드하세요. C열: TikTok Username, D열: Instagram URL/Username')
                        ->disk('local')
                        ->directory('temp-excel-imports')
                        ->visibility('private'),
                ])
                ->requiresConfirmation()
                ->modalHeading('엑셀 데이터 일괄 등록')
                ->modalDescription('엑셀 파일의 모든 시트 데이터를 일괄로 등록합니다. C열의 username으로 TikTok 사용자를 찾아 매칭합니다.')
                ->action(function (array $data) {
                    try {
                        $filePath = storage_path('app/' . $data['file']);

                        // CSV로 임시 변환하여 처리 (PhpSpreadsheet 없이)
                        $fileContent = file_get_contents($filePath);
                        $rows = [];

                        // 파일 확장자 확인
                        $extension = pathinfo($data['file'], PATHINFO_EXTENSION);

                        if (in_array($extension, ['csv', 'txt'])) {
                            // CSV 파일 직접 읽기
                            $handle = fopen($filePath, 'r');
                            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                                $rows[] = $row;
                            }
                            fclose($handle);
                        } else {
                            // Excel 파일의 경우 PhpSpreadsheet 필요
                            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                                throw new \Exception('PhpSpreadsheet 패키지가 설치되지 않았습니다. composer require phpoffice/phpspreadsheet 명령을 실행해주세요.');
                            }

                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

                            // 모든 시트 처리
                            $sheetCount = $spreadsheet->getSheetCount();
                            for ($sheetIndex = 0; $sheetIndex < $sheetCount; $sheetIndex++) {
                                $worksheet = $spreadsheet->getSheet($sheetIndex);
                                $sheetName = mb_convert_encoding($worksheet->getTitle(), 'UTF-8', 'auto'); // 시트명 인코딩 변환
                                $sheetRows = $worksheet->toArray();

                                // 첫 번째 시트는 헤더 제거, 나머지는 그대로 추가
                                if ($sheetIndex === 0) {
                                    array_shift($sheetRows); // 헤더 제거
                                }

                                // 각 행에 시트명 정보 추가하고 인코딩 처리
                                foreach ($sheetRows as &$row) {
                                    // 각 셀의 인코딩 처리
                                    foreach ($row as $key => $value) {
                                        if (is_string($value)) {
                                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
                                        }
                                    }
                                    $row['sheet_name'] = $sheetName;
                                }

                                // 현재 시트의 행들을 전체 rows 배열에 추가
                                $rows = array_merge($rows, $sheetRows);
                            }
                        }

                        $successCount = 0;
                        $failCount = 0;
                        $errors = [];

                        foreach ($rows as $rowIndex => $row) {
                            try {
                                // C열(index 2): TikTok username, D열(index 3): Instagram URL/username
                                $username = isset($row[2]) ? trim(strval($row[2])) : '';
                                $instagramInfo = isset($row[3]) ? trim(strval($row[3])) : '';

                                if (empty($username)) {
                                    $errors[] = "행 " . ($rowIndex + 2) . ": Username이 비어있습니다.";
                                    $failCount++;
                                    continue;
                                }

                                // Instagram URL에서 username 추출
                                $instagramUsername = $instagramInfo;
                                if (!empty($instagramInfo) && str_contains($instagramInfo, 'instagram.com/')) {
                                    // URL에서 username 추출
                                    preg_match('/instagram\.com\/([^\/\?]+)/', $instagramInfo, $matches);
                                    $instagramUsername = isset($matches[1]) ? $matches[1] : $instagramInfo;
                                }

                                // TikTok 사용자 찾기 또는 생성
                                $tiktokUser = TiktokUser::where('username', $username)->first();

                                if (!$tiktokUser) {
                                    // 사용자가 없으면 새로 생성
                                    $tiktokUser = TiktokUser::create([
                                        'username' => $username,
                                        'nickname' => isset($row[1]) ? strval($row[1]) : null, // B열: 이름
                                        'profile_url' => $instagramInfo,
                                    ]);
                                }

                                // 개인정보 생성 또는 업데이트
                                $personalInfo = TiktokUserPersonalInfo::updateOrCreate(
                                    ['tiktok_user_id' => $tiktokUser->id],
                                    [
                                        'name' => isset($row[1]) ? strval($row[1]) : null, // B열: 이름
                                        'email' => null, // 엑셀에 이메일 정보가 없음
                                        'phone' => isset($row[6]) ? strval($row[6]) : null, // G열: 전화번호
                                        'address' => isset($row[4]) ? strval($row[4]) : null, // E열: 주소
                                        'postal_code' => isset($row[5]) ? strval($row[5]) : null, // F열: 우편번호
                                        'category1' => 'Instagram', // 기본값 설정
                                        'category2' => isset($row['sheet_name']) ? strval($row['sheet_name']) : null, // 시트명을 분류2에 저장
                                        'brand_feedback' => isset($row[7]) ? strval($row[7]) : null, // H열: 브랜드 피드백
                                        'repost_permission' => (isset($row[8]) && str_contains(strval($row[8]), '大丈夫')) ? 'Y' : 'P', // I열: 리포스트 허가
                                        'created_at' => isset($row[0]) ? \Carbon\Carbon::parse($row[0]) : now(), // A열: 타임스탬프
                                        'additional_info' => "Instagram: @{$instagramUsername}",
                                    ]
                                );

                                $successCount++;
                            } catch (\Exception $e) {
                                $errors[] = "행 " . ($rowIndex + 2) . ": " . $e->getMessage();
                                $failCount++;
                            }
                        }

                        // 임시 파일 삭제
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // 결과 알림
                        if ($successCount > 0) {
                            $sheetInfo = isset($sheetCount) ? " (총 {$sheetCount}개 시트 처리)" : "";
                            $message = "{$successCount}개의 데이터가 성공적으로 등록되었습니다{$sheetInfo}.";
                            if ($failCount > 0) {
                                $message .= " ({$failCount}개 실패)";
                                if (count($errors) > 0) {
                                    $message .= "\n\n오류 내역:\n" . implode("\n", array_slice($errors, 0, 5));
                                    if (count($errors) > 5) {
                                        $message .= "\n... 외 " . (count($errors) - 5) . "개";
                                    }
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('엑셀 임포트 완료')
                                ->body($message)
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('엑셀 임포트 실패')
                                ->body("데이터를 등록할 수 없습니다.\n" . implode("\n", array_slice($errors, 0, 5)))
                                ->persistent()
                                ->send();
                        }

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('엑셀 파일 처리 오류')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('createUploadRequestsForFiltered')
                ->label('검색 결과 전체 업로드 요청 생성')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\Textarea::make('request_content')
                        ->label('요청사항')
                        ->required()
                        ->rows(4)
                        ->placeholder('예: 제품 리뷰 영상을 만들어 주세요')
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('request_tags')
                        ->label('필수 해시태그/멘션')
                        ->placeholder('#해시태그 @멘션 등을 입력')
                        ->helperText('공백으로 구분하여 여러 개를 입력할 수 있습니다.')
                        ->separator(' ')
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('deadline_date')
                        ->label('게시 기한')
                        ->displayFormat('Y-m-d')
                        ->minDate(now())
                        ->default(now()->addDays(7)),
                ])
                ->requiresConfirmation()
                ->modalHeading('검색 결과 전체에 업로드 요청 생성')
                ->modalDescription(function () {
                    $query = $this->getFilteredTableQuery();
                    $count = $query->count();
                    return "현재 필터와 검색 조건에 맞는 {$count}명의 사용자에게 업로드 요청을 생성합니다.";
                })
                ->action(function (array $data) {
                    $query = $this->getFilteredTableQuery();
                    $records = $query->get();

                    $count = 0;
                    foreach ($records as $record) {
                        if ($record->tiktok_user_id) {
                            TiktokUploadRequest::create([
                                'tiktok_user_id' => $record->tiktok_user_id,
                                'request_content' => $data['request_content'],
                                'request_tags' => $data['request_tags'] ?? [],
                                'deadline_date' => $data['deadline_date'],
                                'requested_at' => now(),
                                'is_uploaded' => false,
                                'is_confirm' => false,
                            ]);
                            $count++;
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('업로드 요청 생성 완료')
                        ->body($count . '개의 업로드 요청이 생성되었습니다.')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
