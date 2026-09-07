<?php

namespace App\Filament\Resources\InventoryReturnResource\Pages;

use App\Filament\Resources\InventoryReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryReturn extends EditRecord
{
    protected static string $resource = InventoryReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
