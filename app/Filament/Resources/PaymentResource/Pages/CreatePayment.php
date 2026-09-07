<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /** @var array<int, int> */
    protected array $applyTo = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->applyTo = $data['apply_to'] ?? [];
        unset($data['apply_to']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $invoices = $this->applyTo
            ? Invoice::whereIn('id', $this->applyTo)->orderBy('invoice_date')->get()
            : Invoice::where('client_id', $this->record->client_id)->outstanding()->orderBy('invoice_date')->get();

        if ($this->record->payment_type !== 'advance_payment') {
            $this->record->allocateTo($invoices);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
