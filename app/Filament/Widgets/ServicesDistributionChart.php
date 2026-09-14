<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
use App\Support\ChartPalette;
use App\Support\LocationContext;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class ServicesDistributionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Services Distribution';

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }

    protected function getData(): array
    {
        $locationId = LocationContext::effectiveFilterId($this->filters);
        $from = Carbon::parse($this->filters['from'] ?? now()->startOfYear())->startOfDay();
        $to = Carbon::parse($this->filters['to'] ?? now())->endOfDay();

        $byGroup = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->when($locationId, fn ($q) => $q->where('invoices.location_id', $locationId))
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->selectRaw('groups.name as group_name, invoice_items.kind as kind')
            ->selectRaw('SUM(invoice_items.line_total_inc_tax) as total')
            ->groupBy('groups.name', 'invoice_items.kind')
            ->get()
            ->groupBy(fn ($r) => $r->group_name ?: ucfirst($r->kind))
            ->map(fn ($rows) => (float) $rows->sum('total'))
            ->sortByDesc(fn ($total) => $total);

        // Cap at the palette's colorblind-safe run and fold the long tail into "Other".
        $top = $byGroup->take(count(ChartPalette::SEQUENCE) - 1);
        $rest = $byGroup->skip($top->count())->sum();
        $slices = $rest > 0 ? $top->merge(['Other' => $rest]) : $top;

        $colors = array_slice(ChartPalette::SEQUENCE, 0, $top->count());
        if ($rest > 0) {
            $colors[] = ChartPalette::GRAY;
        }

        return [
            'datasets' => [
                ['data' => $slices->values()->all(), 'backgroundColor' => $colors],
            ],
            'labels' => $slices->keys()->all(),
        ];
    }
}
