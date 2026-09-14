<?php

namespace App\Filament\Widgets;

use App\Models\Consultation;
use App\Models\CounterSale;
use App\Support\LocationContext;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ClientsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $today = now();

        return [
            Stat::make('Daily Clients', $this->distinctClients($locationId, $today->copy()->startOfDay(), $today->copy()->endOfDay())),
            Stat::make('Monthly Clients', $this->distinctClients($locationId, $today->copy()->startOfMonth(), $today->copy()->endOfMonth())),
        ];
    }

    /** Distinct clients with a consultation or counter sale in the window. */
    private function distinctClients(?int $locationId, Carbon $from, Carbon $to): int
    {
        $consultClients = Consultation::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->whereBetween('consult_date', [$from, $to])->pluck('client_id');

        $saleClients = CounterSale::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->whereBetween('sale_date', [$from, $to])->pluck('client_id');

        return $consultClients->merge($saleClients)->filter()->unique()->count();
    }
}
