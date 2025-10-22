<?php

namespace App\Filament\Admin\Resources\TikTokRepostVideoResource\Pages;

use App\Filament\Admin\Resources\TikTokRepostVideoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTikTokRepostVideos extends ListRecords
{
    protected static string $resource = TikTokRepostVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}