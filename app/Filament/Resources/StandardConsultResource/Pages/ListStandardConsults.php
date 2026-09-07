<?php

namespace App\Filament\Resources\StandardConsultResource\Pages;

use App\Filament\Resources\StandardConsultResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStandardConsults extends ListRecords
{
    protected static string $resource = StandardConsultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
