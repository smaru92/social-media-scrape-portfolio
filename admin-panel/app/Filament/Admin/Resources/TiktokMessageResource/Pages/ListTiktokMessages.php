<?php

namespace App\Filament\Admin\Resources\TiktokMessageResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiktokMessages extends ListRecords
{
    protected static string $resource = TiktokMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
