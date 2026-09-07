<?php

namespace App\Filament\Resources\SpeciesResource\Pages;

use App\Filament\Resources\SpeciesResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSpecies extends ManageRecords
{
    protected static string $resource = SpeciesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
