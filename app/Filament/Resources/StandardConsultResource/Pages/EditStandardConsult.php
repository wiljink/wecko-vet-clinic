<?php

namespace App\Filament\Resources\StandardConsultResource\Pages;

use App\Filament\Resources\StandardConsultResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStandardConsult extends EditRecord
{
    protected static string $resource = StandardConsultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
