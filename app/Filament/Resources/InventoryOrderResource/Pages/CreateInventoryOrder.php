<?php

namespace App\Filament\Resources\InventoryOrderResource\Pages;

use App\Filament\Resources\InventoryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryOrder extends CreateRecord
{
    protected static string $resource = InventoryOrderResource::class;
}
