<?php

namespace App\Filament\Resources\PatientReminderTypeResource\Pages;

use App\Filament\Resources\PatientReminderTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePatientReminderTypes extends ManageRecords
{
    protected static string $resource = PatientReminderTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
