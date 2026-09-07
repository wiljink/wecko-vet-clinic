<?php

namespace App\Filament\Resources\InventoryOrderResource\Pages;

use App\Filament\Resources\InventoryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryOrder extends EditRecord
{
    protected static string $resource = InventoryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
