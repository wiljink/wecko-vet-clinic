<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Resources\ConsultationResource;
use App\Models\Consultation;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditConsultation extends EditRecord
{
    protected static string $resource = ConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('finalize')
                ->label('Finalize & invoice')->icon('heroicon-m-lock-closed')->color('success')->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'finalized' && Auth::user()->can('finalize_consultation'))
                ->action(function () {
                    $invoice = $this->record->finalize();
                    Notification::make()->title("Invoice {$invoice->invoice_no} raised — ₱".number_format($invoice->total, 2))->success()->send();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\Action::make('close')
                ->label('Close consult')->color('gray')
                ->visible(fn () => $this->record->status === 'open')
                ->action(fn () => $this->record->update(['status' => 'closed'])),
            Actions\DeleteAction::make()->visible(fn () => $this->record->status !== 'finalized'),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->isFinalized() && ! Auth::user()->can('reopen_consultation')) {
            Notification::make()->title('This consult is finalized and can only be changed by a senior clinician.')->danger()->send();
            $this->halt();
        }
    }
}
