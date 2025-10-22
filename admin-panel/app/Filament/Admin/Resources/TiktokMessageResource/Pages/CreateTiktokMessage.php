<?php

namespace App\Filament\Admin\Resources\TiktokMessageResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTiktokMessage extends CreateRecord
{
    protected static string $resource = TiktokMessageResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $senderId = $record->tiktok_sender_id;

        if ($senderId) {
            $record->tiktok_message_logs()->update(['tiktok_sender_id' => $senderId]);
        }
    }
}
