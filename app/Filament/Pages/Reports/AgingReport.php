<?php

namespace App\Filament\Pages\Reports;

class AgingReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Aging of Accounts';

    protected static ?string $title = 'Aging of Accounts';

    protected static ?int $navigationSort = 6;

    protected string $builderMethod = 'agingOfAccounts';
}
