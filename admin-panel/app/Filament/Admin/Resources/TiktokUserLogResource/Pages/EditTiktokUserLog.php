<?php

namespace App\Filament\Admin\Resources\TiktokUserLogResource\Pages;

use App\Filament\Admin\Resources\TiktokUserLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokUserLog extends EditRecord
{
    protected static string $resource = TiktokUserLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
