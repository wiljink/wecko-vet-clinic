<?php

namespace App\Filament\Resources\StockReceiptResource\Pages;

use App\Filament\Resources\StockReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockReceipt extends EditRecord
{
    protected static string $resource = StockReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
