<?php

namespace App\Filament\Resources\CardTypeResource\Pages;

use App\Filament\Resources\CardTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCardTypes extends ManageRecords
{
    protected static string $resource = CardTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
