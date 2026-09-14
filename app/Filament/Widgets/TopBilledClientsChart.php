<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Support\ChartPalette;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class TopBilledClientsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top Billed Clients';

    protected static ?int $sort = 8;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getData(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $from = Carbon::parse($this->filters['from'] ?? now()->startOfYear())->startOfDay();
        $to = Carbon::parse($this->filters['to'] ?? now())->endOfDay();

        $rows = Invoice::query()
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->when($locationId, fn ($q) => $q->where('invoices.location_id', $locationId))
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->selectRaw("CONCAT(clients.surname, ', ', clients.given_name) as name, SUM(invoices.total) as total")
            ->groupBy('clients.id', 'clients.surname', 'clients.given_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Billed', 'data' => $rows->pluck('total')->map(fn ($t) => (float) $t)->all(), ...ChartPalette::dataset(ChartPalette::ORANGE, 0.75)],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
