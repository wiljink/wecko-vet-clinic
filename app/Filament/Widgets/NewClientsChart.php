<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\CounterSale;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class NewClientsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'New Clients';

    protected static ?int $sort = 5;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $year = now()->year;

        return [
            'datasets' => [
                ['label' => "Monthly New Clients {$year}", 'data' => $this->monthlyCounts($locationId, $year)],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    /** @return array<int, int> */
    private function monthlyCounts(?int $locationId, int $year): array
    {
        if (! $locationId) {
            $counts = Client::query()->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->groupBy('month')->pluck('total', 'month');

            return collect(range(1, 12))->map(fn ($m) => (int) ($counts[$m] ?? 0))->all();
        }

        // A client is "new" to a branch on the month of their first visit there.
        $consultFirst = Consultation::query()->where('location_id', $locationId)
            ->selectRaw('client_id, MIN(consult_date) as first_visit')->groupBy('client_id')->get();
        $saleFirst = CounterSale::query()->where('location_id', $locationId)
            ->selectRaw('client_id, MIN(sale_date) as first_visit')->groupBy('client_id')->get();

        $firstVisits = $consultFirst->merge($saleFirst)
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->min('first_visit'));

        $counts = array_fill(1, 12, 0);
        foreach ($firstVisits as $date) {
            $date = \Illuminate\Support\Carbon::parse($date);
            if ($date->year === $year) {
                $counts[$date->month]++;
            }
        }

        return array_values($counts);
    }
}
