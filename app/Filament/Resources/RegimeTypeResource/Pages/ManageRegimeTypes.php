<?php

namespace App\Filament\Resources\RegimeTypeResource\Pages;

use App\Filament\Resources\RegimeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRegimeTypes extends ManageRecords
{
    protected static string $resource = RegimeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
