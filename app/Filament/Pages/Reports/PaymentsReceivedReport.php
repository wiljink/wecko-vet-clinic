<?php

namespace App\Filament\Pages\Reports;

class PaymentsReceivedReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payments Received';

    protected static ?string $title = 'Payments Received';

    protected static ?int $navigationSort = 2;

    protected string $builderMethod = 'paymentsReceived';
}
