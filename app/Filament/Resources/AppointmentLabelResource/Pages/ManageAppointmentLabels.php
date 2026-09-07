<?php

namespace App\Filament\Resources\AppointmentLabelResource\Pages;

use App\Filament\Resources\AppointmentLabelResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAppointmentLabels extends ManageRecords
{
    protected static string $resource = AppointmentLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
