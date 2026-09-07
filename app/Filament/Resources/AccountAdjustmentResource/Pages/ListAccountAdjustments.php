<?php

namespace App\Filament\Resources\AccountAdjustmentResource\Pages;

use App\Filament\Resources\AccountAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountAdjustments extends ListRecords
{
    protected static string $resource = AccountAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
