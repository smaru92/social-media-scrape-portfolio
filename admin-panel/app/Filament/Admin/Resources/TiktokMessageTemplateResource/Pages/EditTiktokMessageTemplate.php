<?php

namespace App\Filament\Admin\Resources\TiktokMessageTemplateResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTiktokMessageTemplate extends EditRecord
{
    protected static string $resource = TiktokMessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['message_header_json'])) {
            foreach ($data['message_header_json'] as &$item) {
                $item['text'] = preg_replace('/\r?\n/', ' ', $item['text']);
            }
        }
        
        if (isset($data['message_body_json'])) {
            foreach ($data['message_body_json'] as &$item) {
                $item['text'] = preg_replace('/\r?\n/', ' ', $item['text']);
            }
        }
        
        if (isset($data['message_footer_json'])) {
            foreach ($data['message_footer_json'] as &$item) {
                $item['text'] = preg_replace('/\r?\n/', ' ', $item['text']);
            }
        }
        
        return $data;
    }
}
