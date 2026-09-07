<?php

namespace App\Filament\Resources\ReferralResource\Pages;

use App\Filament\Resources\ReferralResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageReferrals extends ManageRecords
{
    protected static string $resource = ReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
