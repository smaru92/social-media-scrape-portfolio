<?php

namespace App\Filament\Admin\Resources\TiktokVideoResource\Pages;

use App\Filament\Admin\Resources\TiktokVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokVideo extends EditRecord
{
    protected static string $resource = TiktokVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}