<?php

namespace App\Filament\Resources\AppointmentReasonResource\Pages;

use App\Filament\Resources\AppointmentReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAppointmentReasons extends ManageRecords
{
    protected static string $resource = AppointmentReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
