<?php

namespace App\Filament\Pages\Reports;

class InventoryValuationReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Inventory Valuation';

    protected static ?string $title = 'Inventory Valuation';

    protected static ?int $navigationSort = 8;

    protected string $builderMethod = 'inventoryValuation';
}
