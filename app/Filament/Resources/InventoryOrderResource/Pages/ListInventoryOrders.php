<?php

namespace App\Filament\Resources\InventoryOrderResource\Pages;

use App\Filament\Resources\InventoryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryOrders extends ListRecords
{
    protected static string $resource = InventoryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
