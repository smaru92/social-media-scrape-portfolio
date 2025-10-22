<?php

namespace App\Filament\Exports;

use App\Models\Seller;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;

class SellerExporter extends Exporter
{
    protected static ?string $model = Seller::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('instagram_name'),
            ExportColumn::make('name'),
            ExportColumn::make('memo'),
            ExportColumn::make('instagram_name')->label('LINK')
                ->prefix('https://instagram.com/')
                ->suffix('./'),
                #->state(fn (Model $model): string => 'https://instagram.com/' . $model->instagram_name . '/'),
            ExportColumn::make('tags')->state(function ($record) {
                return $record->tagsWithType('seller')->pluck('name')->implode(', ');
            }),
            ExportColumn::make('product_tags')->state(function ($record) {
                return $record->tagsWithType('product_inst')->pluck('name')->implode(', ');
            }),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your seller export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
