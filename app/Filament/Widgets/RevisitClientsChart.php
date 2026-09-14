<?php

namespace App\Filament\Widgets;

use App\Models\Consultation;
use App\Models\CounterSale;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RevisitClientsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revisit Clients';

    protected static ?int $sort = 6;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $year = now()->year;

        $visits = $this->allVisits($locationId); // client_id => sorted Collection<Carbon>

        $counts = array_fill(1, 12, 0);
        foreach ($visits as $dates) {
            $monthsSeen = [];
            foreach ($dates as $i => $date) {
                if ($i === 0) {
                    continue; // first-ever visit isn't a revisit
                }
                $month = $date->month;
                if ($date->year === $year && ! isset($monthsSeen[$month])) {
                    $counts[$month]++;
                    $monthsSeen[$month] = true;
                }
            }
        }

        return [
            'datasets' => [
                ['label' => "Monthly Revisit Clients {$year}", 'data' => array_values($counts)],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    /** @return Collection<int, Collection<int, Carbon>> client_id => sorted visit dates */
    private function allVisits(?int $locationId): Collection
    {
        $consultRows = Consultation::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get(['client_id', 'consult_date'])->map(fn ($c) => ['client_id' => $c->client_id, 'date' => $c->consult_date]);
        $saleRows = CounterSale::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get(['client_id', 'sale_date'])->map(fn ($s) => ['client_id' => $s->client_id, 'date' => $s->sale_date]);

        return $consultRows->merge($saleRows)
            ->filter(fn ($r) => $r['client_id'])
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->pluck('date')->map(fn ($d) => Carbon::parse($d))->sort()->values());
    }
}
