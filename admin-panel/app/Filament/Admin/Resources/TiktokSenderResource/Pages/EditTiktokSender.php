<?php

namespace App\Filament\Admin\Resources\TiktokSenderResource\Pages;

use App\Filament\Admin\Resources\TiktokSenderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokSender extends EditRecord
{
    protected static string $resource = TiktokSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
