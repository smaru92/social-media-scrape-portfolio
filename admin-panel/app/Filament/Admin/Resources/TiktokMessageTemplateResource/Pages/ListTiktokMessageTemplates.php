<?php

namespace App\Filament\Admin\Resources\TiktokMessageTemplateResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiktokMessageTemplates extends ListRecords
{
    protected static string $resource = TiktokMessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
