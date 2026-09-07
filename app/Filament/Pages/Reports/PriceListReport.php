<?php

namespace App\Filament\Pages\Reports;

class PriceListReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Price List';

    protected static ?string $title = 'Price List';

    protected static ?int $navigationSort = 9;

    protected string $builderMethod = 'priceList';
}
