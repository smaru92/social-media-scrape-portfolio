<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TikTokBrandAccountsTemplateExport
{
    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 헤더 설정
        $headers = ['계정명', '브랜드명', '국가', '카테고리', '프로필_URL'];
        $sheet->fromArray($headers, NULL, 'A1');

        // 예시 데이터
        $exampleData = [
            ['example_account', '예시 브랜드', 'KR', '패션', 'https://www.tiktok.com/@example_account'],
            ['brand_official', '공식 브랜드', 'US', '뷰티', 'https://www.tiktok.com/@brand_official'],
        ];
        $sheet->fromArray($exampleData, NULL, 'A2');

        // 컬럼 넓이 설정
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(50);

        // 헤더 스타일
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // 테두리 설정
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A1:E3')->applyFromArray($borderStyle);

        // 파일 생성
        $writer = new Xlsx($spreadsheet);
        $fileName = '브랜드계정_템플릿.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        // 다운로드 응답 반환
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}