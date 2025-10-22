<?php

namespace App\Filament\Admin\Resources\TiktokUserLogResource\Pages;

use App\Filament\Admin\Resources\TiktokUserLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiktokUserLogs extends ListRecords
{
    protected static string $resource = TiktokUserLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
