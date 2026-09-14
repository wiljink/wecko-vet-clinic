<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Route;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('history')
                ->label('History PDF')
                ->icon('heroicon-m-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('patients.history', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => Route::has('patients.history')),
        ];
    }
}
