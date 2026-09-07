<?php

namespace App\Filament\Resources\VaccineResource\Pages;

use App\Filament\Resources\VaccineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVaccines extends ListRecords
{
    protected static string $resource = VaccineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
