<?php

namespace App\Filament\Resources\LeaveVacationBreakResource\Pages;

use App\Filament\Resources\LeaveVacationBreakResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveVacationBreaks extends ManageRecords
{
    protected static string $resource = LeaveVacationBreakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
