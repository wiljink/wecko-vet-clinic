<?php

namespace App\Filament\Resources\BankingBatchResource\Pages;

use App\Filament\Resources\BankingBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankingBatches extends ListRecords
{
    protected static string $resource = BankingBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
