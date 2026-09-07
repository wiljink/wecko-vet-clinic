<?php

namespace App\Filament\Resources\StockReceiptResource\Pages;

use App\Filament\Resources\StockReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockReceipts extends ListRecords
{
    protected static string $resource = StockReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
