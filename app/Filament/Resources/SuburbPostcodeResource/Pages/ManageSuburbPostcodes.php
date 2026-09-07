<?php

namespace App\Filament\Resources\SuburbPostcodeResource\Pages;

use App\Filament\Resources\SuburbPostcodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSuburbPostcodes extends ManageRecords
{
    protected static string $resource = SuburbPostcodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
