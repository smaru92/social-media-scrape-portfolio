<?php

namespace App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Pages;

use App\Filament\Admin\Resources\TiktokAutoDmConfigResource;
use App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Widgets\TargetUsersWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokAutoDmConfig extends EditRecord
{
    protected static string $resource = TiktokAutoDmConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '자동 DM 설정이 저장되었습니다.';
    }

    protected function getFooterWidgets(): array
    {
        return [
            TargetUsersWidget::make([
                'record' => $this->record,
            ]),
        ];
    }
}
