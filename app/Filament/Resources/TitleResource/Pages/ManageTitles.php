<?php

namespace App\Filament\Resources\TitleResource\Pages;

use App\Filament\Resources\TitleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTitles extends ManageRecords
{
    protected static string $resource = TitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
