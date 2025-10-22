<?php

namespace App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\Pages;

use App\Filament\Admin\Resources\TiktokUserPersonalInfoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokUserPersonalInfo extends EditRecord
{
    protected static string $resource = TiktokUserPersonalInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}