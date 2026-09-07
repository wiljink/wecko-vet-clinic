<?php

namespace App\Filament\Resources\TillSessionResource\Pages;

use App\Filament\Resources\TillSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTillSessions extends ListRecords
{
    protected static string $resource = TillSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
