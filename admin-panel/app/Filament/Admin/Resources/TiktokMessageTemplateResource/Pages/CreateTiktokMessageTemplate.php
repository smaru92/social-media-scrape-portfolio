<?php

namespace App\Filament\Admin\Resources\TiktokMessageTemplateResource\Pages;

use App\Filament\Admin\Resources\TiktokMessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTiktokMessageTemplate extends CreateRecord
{
    protected static string $resource = TiktokMessageTemplateResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
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
