<?php

namespace App\Filament\Resources\AppointmentStatusResource\Pages;

use App\Filament\Resources\AppointmentStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAppointmentStatuses extends ManageRecords
{
    protected static string $resource = AppointmentStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
