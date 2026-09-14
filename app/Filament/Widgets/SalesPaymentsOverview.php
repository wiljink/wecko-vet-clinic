<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\LocationContext;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesPaymentsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $today = now();

        $dailySales = Invoice::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->whereDate('invoice_date', $today)->sum('total');
        $monthlySales = Invoice::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->whereYear('invoice_date', $today->year)->whereMonth('invoice_date', $today->month)->sum('total');

        $dailyPayments = Payment::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('is_refund', false)->whereDate('received_at', $today)->sum('amount');
        $monthlyPayments = Payment::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('is_refund', false)->whereYear('received_at', $today->year)->whereMonth('received_at', $today->month)->sum('amount');

        return [
            Stat::make('Daily Sales', '₱'.number_format((float) $dailySales, 2)),
            Stat::make('Monthly Sales', '₱'.number_format((float) $monthlySales, 2)),
            Stat::make('Daily Payments', '₱'.number_format((float) $dailyPayments, 2)),
            Stat::make('Monthly Payments', '₱'.number_format((float) $monthlyPayments, 2)),
        ];
    }
}
