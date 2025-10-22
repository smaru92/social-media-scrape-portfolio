<?php

namespace App\Filament\Admin\Resources\TiktokUserResource\Pages;

use App\Filament\Admin\Resources\TiktokUserResource;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Http;
use App\Models\TiktokUserLog;
use App\Models\TiktokMessage;
use App\Models\TiktokSender;
use App\Models\TiktokMessageTemplate;
use App\Models\TiktokUser;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListTiktokUsers extends ListRecords
{
    protected static string $resource = TiktokUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('uploadExcel')
                ->label('엑셀 일괄 업로드')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalDescription('엑셀 파일을 업로드하여 틱톡 사용자를 일괄 등록합니다.')
                ->modalSubmitActionLabel('업로드')
                ->modalCancelActionLabel('닫기')
                ->extraModalFooterActions([
                    \Filament\Actions\Action::make('downloadTemplate')
                        ->label('엑셀 양식 다운로드')
                        ->color('gray')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function () {
                            return $this->downloadExcelTemplate();
                        })
                        ->close(false),
                ])
                ->form([
                    Select::make('country')
                        ->label('국가')
                        ->options(TiktokUserResource::getCountryOptions())
                        ->searchable()
                        ->required()
                        ->placeholder('국가를 선택하세요'),
                    FileUpload::make('excel_file')
                        ->label('엑셀 파일')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel'
                        ])
                        ->maxSize(10240)
                        ->helperText('엑셀 양식에 맞춰 작성된 파일을 업로드하세요. (최대 10MB)'),
                ])
                ->action(function (array $data) {
                    return $this->uploadExcel($data);
                }),
            Actions\Action::make('collect')
                ->label('틱톡사용자 수집')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->modalDescription('입력한 내용을 토대로 틱톡 사용자를 검색후 추가합니다. 요청후 처리까지 5~10분 소요됩니다.')
                ->form([
                    TextInput::make('keyword')
                        ->label('키워드')
                        ->required(),
                    TextInput::make('min_followers')
                        ->label('최소 팔로워')
                        ->numeric()
                        ->required()
                        ->default(0),
                ])
                ->action(function (array $data) {
                    // 1. TiktokUserLog에 데이터 삽입
                    $tiktokUserLog = TiktokUserLog::create([
                        'keyword' => $data['keyword'],
                        'min_followers' => $data['min_followers'],
                        'search_user_count' => 0, // 초기값
                        'save_user_count' => 0,   // 초기값
                        'is_error' => 0,    // 초기 상태
                    ]);

                    $apiUrl = config('app.api_url') . '/api/v1/tiktok/scrape';

                    // 비동기 처리를 위해 timeout을 짧게 설정하고 예외 처리
                    try {
                        $response = Http::timeout(5)->post($apiUrl, [
                            'keyword' => $data['keyword'],
                            'min_followers' => $data['min_followers'],
                            'tiktok_user_log_id' => $tiktokUserLog->id,
                        ]);
                    } catch (\Exception $e) {
                        // API 호출은 백그라운드에서 처리되므로 timeout 무시
                    }

                    // API 성공/실패와 관계없이 처리중 메시지 표시
                    Notification::make()
                        ->title('수집 요청 완료')
                        ->body('틱톡 사용자 수집이 백그라운드에서 처리됩니다. 5~10분 후 결과를 확인해주세요.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('send_message')
                ->label('검색결과 메시지 전송')
                ->color('primary')
                ->icon('heroicon-o-paper-airplane')
                ->modalDescription(function () {
                    $query = $this->getFilteredTableQuery();
                    $count = $query->count();

                    // 현재 검색 파라미터 가져오기
                    $searchTerm = request()->input('tableSearch.username') ??
                                 request()->input('tableSearch.nickname') ??
                                 request()->input('tableSearch.keyword') ?? '';
                    $filters = [];

                    // 협업자 필터 체크
                    if (request()->has('tableFilters.is_collaborator.value')) {
                        $collaboratorFilter = request()->input('tableFilters.is_collaborator.value');
                        if ($collaboratorFilter === '1') {
                            $filters[] = '협업자';
                        } elseif ($collaboratorFilter === '0') {
                            $filters[] = '일반 사용자';
                        }
                    }

                    // 상태 필터 체크
                    if (request()->has('tableFilters.status.value')) {
                        $status = request()->input('tableFilters.status.value');
                        if ($status) {
                            $statusLabel = TiktokUser::getStatusLabels()[$status] ?? $status;
                            $filters[] = "상태: {$statusLabel}";
                        }
                    }

                    $description = "현재 검색된 모든 사용자에게 전송할 메시지 예약 데이터를 만듭니다.\n\n";
                    $description .= "📊 대상 인원: {$count}명\n";

                    if ($searchTerm) {
                        $description .= "🔍 검색 키워드: \"{$searchTerm}\"\n";
                    }

                    if (!empty($filters)) {
                        $description .= "🏷️ 필터: " . implode(', ', $filters) . "\n";
                    }

                    return $description;
                })
                ->form([
                    Select::make('tiktok_message_template_id')
                        ->label('메시지 템플릿')
                        ->options(TiktokMessageTemplate::pluck('title', 'id'))
                        ->required(),
                    Select::make('tiktok_sender_id')
                        ->label('발신 계정')
                        ->options(TiktokSender::pluck('name', 'id'))
                        ->required(),
                    TextInput::make('title')
                        ->label('메시지 제목')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('메시지 제목을 입력하세요'),
                    Toggle::make('is_auto')
                        ->label('자동 발송')
                        ->default(false)
                        ->reactive()
                        ->helperText('자동 발송을 선택하면 지정한 시간에 자동으로 발송이 시작됩니다. 그렇지 않으면 수동으로 발송을 진행해야합니다.'),
                    DateTimePicker::make('start_at')
                        ->label('전송 시작 시간')
                        ->required()
                        ->visible(fn (callable $get) => $get('is_auto')),
                    DateTimePicker::make('end_at')
                        ->label('전송 종료 시간')
                        ->visible(fn (callable $get) => $get('is_auto')),
                    Toggle::make('send_immediately')
                        ->label('즉시 수동 전송')
                        ->default(false)
                        ->visible(fn ($get) => !$get('is_auto'))
                        ->reactive()
                        ->helperText('체크하면 메시지 생성 직후 바로 전송을 시작합니다.'),
                ])
->action(function (array $data) {
                    // 현재 페이지에서 검색된 모든 사용자 ID를 가져옴
                    $query = $this->getFilteredTableQuery();
                    $userIds = $query->pluck('id')->toArray();

                    if (empty($userIds)) {
                        Notification::make()
                            ->title('전송 실패')
                            ->body('전송할 사용자가 없습니다.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // TiktokMessage 생성
                    $tiktokMessage = TiktokMessage::create([
                        'title' => $data['title'],
                        'tiktok_sender_id' => $data['tiktok_sender_id'],
                        'tiktok_message_template_id' => $data['tiktok_message_template_id'],
                        'is_auto' => $data['is_auto'] ?? false,
                        'is_complete' => false,
                        'start_at' => $data['start_at'] ?? null,
                        'end_at' => $data['end_at'] ?? null,
                    ]);

                    // 선택된 사용자들을 메시지에 연결
                    $tiktokMessage->tiktok_users()->sync($userIds);

                    // 자동 발송이 아니고 즉시 수동 전송이 체크된 경우
                    if (!($data['is_auto'] ?? false) && ($data['send_immediately'] ?? false)) {
                        try {
                            // 발신 계정의 세션 파일 경로 가져오기
                            $sender = $tiktokMessage->tiktok_sender;
                            $sessionFilePath = $sender->session_file_path ?? null;

                            // 템플릿 정보 가져오기
                            $template = $tiktokMessage->tiktok_message_template;

                            // 사용자 목록 가져오기
                            $usernames = $tiktokMessage->tiktok_users()->pluck('username')->filter()->toArray();
                            if (empty($usernames)) {
                                $usernames = $tiktokMessage->tiktok_users()->pluck('nickname')->filter()->toArray();
                            }

                            // API 호출 데이터 구성
                            $apiData = [
                                'usernames' => $usernames,
                                'template_code' => $template->template_code,
                                'session_file_path' => $sessionFilePath,
                                'message_id' => $tiktokMessage->id,
                            ];

                            $apiUrl = config('app.api_url') . '/api/v1/tiktok/send_message';

                            $response = Http::timeout(15)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post($apiUrl, $apiData);

                            if ($response->successful()) {
                                $tiktokMessage->update([
                                    'send_status' => 'sending'
                                ]);

                                Notification::make()
                                    ->title('메시지 생성 및 전송 시작')
                                    ->body(count($userIds) . '명의 사용자를 대상으로 메시지가 생성되고 전송이 시작되었습니다.')
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception('API 서버 응답 오류');
                            }
                        } catch (\Exception $e) {
                            // 전송 실패해도 메시지는 생성됨
                            Notification::make()
                                ->title('메시지 생성 완료, 전송 시작 실패')
                                ->body('메시지는 생성되었지만 전송 시작에 실패했습니다. 메시지 관리에서 수동으로 전송할 수 있습니다.')
                                ->warning()
                                ->send();
                        }

                        return;
                    }

                    // 자동 발송이 아니고 즉시 전송도 선택하지 않은 경우
                    if (!($data['is_auto'] ?? false)) {
                        Notification::make()
                            ->title('메시지 생성 완료')
                            ->body(count($userIds) . '명의 사용자를 대상으로 수동 발송 메시지가 생성되었습니다. 메시지 관리에서 수동으로 전송할 수 있습니다.')
                            ->success()
                            ->send();
                        return;
                    }

                    // 자동 발송의 경우
                    Notification::make()
                        ->title('메시지 생성 완료')
                        ->body(count($userIds) . '명의 사용자를 대상으로 자동 발송 메시지가 생성되었습니다. 지정된 시간에 자동으로 발송됩니다.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('updateFilteredStatus')
                ->label('검색결과 전체 상태 변경')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->modalDescription(function () {
                    $count = $this->getFilteredTableQuery()->count();
                    return "현재 검색 조건에 맞는 {$count}명의 사용자 상태를 일괄 변경합니다.";
                })
                ->form([
                    Select::make('status')
                        ->label('변경할 상태')
                        ->options(TiktokUser::getStatusLabels())
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $query = $this->getFilteredTableQuery();
                    $count = $query->count();

                    if ($count === 0) {
                        Notification::make()
                            ->title('변경 실패')
                            ->body('변경할 사용자가 없습니다.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 상태 업데이트
                    $query->update(['status' => $data['status']]);

                    $statusLabel = TiktokUser::getStatusLabels()[$data['status']];

                    Notification::make()
                        ->title('상태 변경 완료')
                        ->body("{$count}명의 사용자 상태가 '{$statusLabel}'로 변경되었습니다.")
                        ->success()
                        ->send();
                })
        ];
    }

    protected function downloadExcelTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 헤더 설정 (1행)
        $headers = [
            'A1' => '기간',
            'B1' => '크리에이터 명',
            'C1' => 'NST 계정',
            'D1' => '팔로워 수',
            'E1' => '판매액($)',
            'F1' => '총 상품 수',
            'G1' => '라이브 스트리밍',
            'H1' => '라이브 스트리밍 판매액($)',
            'I1' => '동영상',
            'J1' => '동영상 판매액($)',
            'K1' => '조회수',
            'L1' => '크리에이터 첫 게시물 시간',
            'M1' => 'Kalodata 링크',
            'N1' => 'TikTok 링크',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        // 컬럼 너비 자동 조정
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 예시 데이터 추가 (2행)
        $sheet->setCellValue('A2', '2024-01-01 ~ 2024-01-31');
        $sheet->setCellValue('B2', 'example_creator');
        $sheet->setCellValue('C2', '@example_account');
        $sheet->setCellValue('D2', '10000');
        $sheet->setCellValue('E2', '5000');
        $sheet->setCellValue('F2', '50');
        $sheet->setCellValue('G2', '10');
        $sheet->setCellValue('H2', '2000');
        $sheet->setCellValue('I2', '100');
        $sheet->setCellValue('J2', '3000');
        $sheet->setCellValue('K2', '500000');
        $sheet->setCellValue('L2', '2024-01-01 10:00:00');
        $sheet->setCellValue('M2', 'https://www.kalodata.com/example');
        $sheet->setCellValue('N2', 'https://www.tiktok.com/@example_account');

        // 임시 파일로 저장
        $writer = new Xlsx($spreadsheet);
        $fileName = 'tiktok_users_template_' . date('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/temp/' . $fileName);

        // temp 디렉토리가 없으면 생성
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    protected function uploadExcel(array $data)
    {
        try {
            $country = $data['country'];

            // Filament FileUpload는 livewire-tmp 경로를 반환합니다
            $uploadedFile = $data['excel_file'];

            // 실제 파일 경로 가져오기
            $filePath = storage_path('app/public/' . $uploadedFile);

            // livewire-tmp 경로 시도
            if (!file_exists($filePath)) {
                $filePath = storage_path('app/livewire-tmp/' . $uploadedFile);
            }

            // Storage::disk('public') 경로 시도
            if (!file_exists($filePath)) {
                $filePath = Storage::disk('public')->path($uploadedFile);
            }

            // Storage 기본 경로 시도
            if (!file_exists($filePath)) {
                $filePath = Storage::path($uploadedFile);
            }

            if (!file_exists($filePath)) {
                Notification::make()
                    ->title('업로드 실패')
                    ->body('업로드된 파일을 찾을 수 없습니다. 경로: ' . $uploadedFile)
                    ->danger()
                    ->send();
                return;
            }

            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // 헤더 제거 (1행)
            array_shift($rows);

            $successCount = 0;
            $skipCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // 엑셀 행 번호 (헤더 제외)

                // 빈 행 건너뛰기
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // NST 계정 (C열, index 2)
                    $username = trim($row[2] ?? '');

                    if (empty($username)) {
                        $skipCount++;
                        continue;
                    }

                    // @ 기호 제거
                    $username = ltrim($username, '@');

                    // 이미 존재하는지 확인
                    $existingUser = TiktokUser::where('username', $username)->first();

                    if ($existingUser) {
                        // 기존 사용자 업데이트
                        $existingUser->update([
                            'nickname' => trim($row[1] ?? ''), // 크리에이터 명
                            'followers' => is_numeric($row[3] ?? 0) ? (int)$row[3] : 0, // 팔로워 수
                            'profile_url' => trim($row[13] ?? ''), // TikTok 링크
                            'country' => $country,
                        ]);
                        $successCount++;
                    } else {
                        // 신규 사용자 생성
                        TiktokUser::create([
                            'username' => $username,
                            'nickname' => trim($row[1] ?? ''), // 크리에이터 명
                            'followers' => is_numeric($row[3] ?? 0) ? (int)$row[3] : 0, // 팔로워 수
                            'profile_url' => trim($row[13] ?? ''), // TikTok 링크
                            'country' => $country,
                            'status' => TiktokUser::STATUS_UNCONFIRMED,
                            'is_collaborator' => false,
                        ]);
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "행 {$rowNumber}: " . $e->getMessage();
                }
            }

            // 결과 메시지
            $message = "처리 완료\n";
            $message .= "✅ 성공: {$successCount}건\n";
            if ($skipCount > 0) {
                $message .= "⏭️ 건너뛰기: {$skipCount}건\n";
            }
            if ($errorCount > 0) {
                $message .= "❌ 실패: {$errorCount}건\n";
                $message .= "\n오류 상세:\n" . implode("\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= "\n... 외 " . (count($errors) - 5) . "건";
                }
            }

            Notification::make()
                ->title('엑셀 업로드 완료')
                ->body($message)
                ->success()
                ->send();

            // 임시 파일 삭제
            Storage::delete($data['excel_file']);

        } catch (\Exception $e) {
            Notification::make()
                ->title('업로드 실패')
                ->body('엑셀 파일 처리 중 오류가 발생했습니다: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
