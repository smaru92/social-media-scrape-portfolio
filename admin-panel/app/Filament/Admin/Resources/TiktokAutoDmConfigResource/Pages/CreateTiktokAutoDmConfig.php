<?php

namespace App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Pages;

use App\Filament\Admin\Resources\TiktokAutoDmConfigResource;
use App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Widgets\TargetUsersWidget;
use Filament\Resources\Pages\CreateRecord;

class CreateTiktokAutoDmConfig extends CreateRecord
{
    protected static string $resource = TiktokAutoDmConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '자동 DM 설정이 생성되었습니다.';
    }

    protected function getFooterWidgets(): array
    {
        return [
            TargetUsersWidget::make([
                'record' => null,
            ]),
        ];
    }
}
