<?php

namespace App\Filament\Pages\Reports;

class TransactionSummaryReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Transaction Summary';

    protected static ?string $title = 'Transaction Summary';

    protected static ?int $navigationSort = 5;

    protected string $builderMethod = 'transactionSummary';
}
