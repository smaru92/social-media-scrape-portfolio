<?php

namespace App\Filament\Admin\Resources\TiktokUploadRequestResource\Pages;

use App\Filament\Admin\Resources\TiktokUploadRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokUploadRequest extends EditRecord
{
    protected static string $resource = TiktokUploadRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}