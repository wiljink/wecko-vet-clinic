<?php

namespace App\Filament\Pages\Reports;

class SalesVatReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Sales & VAT';

    protected static ?string $title = 'Sales & VAT';

    protected static ?int $navigationSort = 1;

    protected string $builderMethod = 'salesVat';
}
