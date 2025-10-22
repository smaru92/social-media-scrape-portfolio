<?php

namespace App\Filament\Admin\Resources\TiktokUserResource\Pages;

use App\Filament\Admin\Resources\TiktokUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokUser extends EditRecord
{
    protected static string $resource = TiktokUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
