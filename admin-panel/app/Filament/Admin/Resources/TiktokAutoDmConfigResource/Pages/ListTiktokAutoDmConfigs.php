<?php

namespace App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Pages;

use App\Filament\Admin\Resources\TiktokAutoDmConfigResource;
use App\Models\TiktokMessageLog;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListTiktokAutoDmConfigs extends ListRecords
{
    protected static string $resource = TiktokAutoDmConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('daily_status')
                ->label('오늘 발송 현황')
                ->color('info')
                ->icon('heroicon-o-information-circle')
                ->modalHeading('오늘 DM 발송 현황')
                ->modalContent(function () {
                    $todaySentCount = TiktokMessageLog::whereDate('created_at', today())
                        ->where('result', 'success')
                        ->count();
                    $remaining = 100 - $todaySentCount;

                    return view('filament.admin.modals.daily-dm-status', [
                        'sent' => $todaySentCount,
                        'remaining' => $remaining,
                        'total' => 100,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('닫기'),
            Actions\CreateAction::make()
                ->label('새 설정 추가'),
        ];
    }
}
