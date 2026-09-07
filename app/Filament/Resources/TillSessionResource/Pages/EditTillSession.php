<?php

namespace App\Filament\Resources\TillSessionResource\Pages;

use App\Filament\Resources\TillSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTillSession extends EditRecord
{
    protected static string $resource = TillSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
