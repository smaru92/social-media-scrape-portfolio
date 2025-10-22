<?php

namespace App\Filament\Admin\Resources\TiktokVideoResource\Pages;

use App\Filament\Admin\Resources\TiktokVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiktokVideos extends ListRecords
{
    protected static string $resource = TiktokVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}