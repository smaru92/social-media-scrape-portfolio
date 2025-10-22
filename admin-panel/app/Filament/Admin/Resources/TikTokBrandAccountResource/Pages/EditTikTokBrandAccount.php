<?php

namespace App\Filament\Admin\Resources\TikTokBrandAccountResource\Pages;

use App\Filament\Admin\Resources\TikTokBrandAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTikTokBrandAccount extends EditRecord
{
    protected static string $resource = TikTokBrandAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}