<?php

namespace App\Filament\Pages\Reports;

class CashSalesReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Cash Sales';

    protected static ?string $title = 'Cash Sales';

    protected static ?int $navigationSort = 3;

    protected string $builderMethod = 'cashSales';
}
