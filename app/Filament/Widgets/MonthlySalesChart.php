<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Support\ChartPalette;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlySalesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Monthly Sales';

    protected static ?int $sort = 3;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $thisYear = now()->year;
        $lastYear = $thisYear - 1;

        return [
            'datasets' => [
                ['label' => (string) $lastYear, 'data' => $this->monthlyTotals($locationId, $lastYear), ...ChartPalette::dataset(ChartPalette::BLUE)],
                ['label' => (string) $thisYear, 'data' => $this->monthlyTotals($locationId, $thisYear), ...ChartPalette::dataset(ChartPalette::ORANGE)],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    /** @return array<int, float> */
    private function monthlyTotals(?int $locationId, int $year): array
    {
        $totals = Invoice::query()->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->whereYear('invoice_date', $year)
            ->selectRaw('MONTH(invoice_date) as month, SUM(total) as total')
            ->groupBy('month')->pluck('total', 'month');

        return collect(range(1, 12))->map(fn ($m) => (float) ($totals[$m] ?? 0))->all();
    }
}
