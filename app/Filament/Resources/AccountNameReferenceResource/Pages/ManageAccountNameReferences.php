<?php

namespace App\Filament\Resources\AccountNameReferenceResource\Pages;

use App\Filament\Resources\AccountNameReferenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountNameReferences extends ManageRecords
{
    protected static string $resource = AccountNameReferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
