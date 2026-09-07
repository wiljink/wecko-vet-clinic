<?php

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Filament\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockTake extends EditRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
