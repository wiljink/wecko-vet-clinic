<?php

namespace App\Filament\Resources\CounterSaleResource\Pages;

use App\Filament\Resources\CounterSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCounterSale extends EditRecord
{
    protected static string $resource = CounterSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
