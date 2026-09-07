<?php

namespace App\Filament\Pages\Reports;

class ReconciliationReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Account Reconciliation';

    protected static ?string $title = 'Account Reconciliation';

    protected static ?int $navigationSort = 7;

    protected string $builderMethod = 'accountReconciliation';
}
