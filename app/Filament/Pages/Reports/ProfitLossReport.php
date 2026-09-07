<?php

namespace App\Filament\Pages\Reports;

class ProfitLossReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Sales / Profit';

    protected static ?string $title = 'Sales / Profit';

    protected static ?int $navigationSort = 4;

    protected string $builderMethod = 'profitAndLoss';
}
