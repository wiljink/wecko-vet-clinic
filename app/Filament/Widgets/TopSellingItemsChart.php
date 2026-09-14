<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
use App\Support\ChartPalette;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class TopSellingItemsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top Selling Items';

    protected static ?int $sort = 7;

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

        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNotNull('invoice_items.product_id')
            ->when($locationId, fn ($q) => $q->where('invoices.location_id', $locationId))
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->selectRaw('invoice_items.description as name, SUM(invoice_items.qty) as qty')
            ->groupBy('invoice_items.description')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Qty sold', 'data' => $rows->pluck('qty')->map(fn ($q) => (float) $q)->all(), ...ChartPalette::dataset(ChartPalette::BLUE, 0.75)],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
