<?php

namespace App\Filament\Admin\Resources\TiktokMessageLogResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokMessageLog extends EditRecord
{
    protected static string $resource = TiktokMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
