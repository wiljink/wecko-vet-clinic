<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\Cell;
use App\Support\Reports\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared behaviour for every report page: a from/to filter with quick presets,
 * on-screen render and CSV / PDF export. Subclasses set $builderMethod.
 */
abstract class BaseReport extends Page
{
    protected static ?string $navigationGroup = 'Reports';

    protected static string $view = 'filament.pages.reports.base';

    /** ReportBuilder method that builds this page. */
    protected string $builderMethod = '';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_any_report') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->firstOfQuarter(),
            'to' => now(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('from')->required()->native(false)->maxDate(now())->closeOnDateSelection(),
            DatePicker::make('to')->required()->native(false)->maxDate(now())->closeOnDateSelection(),
        ])->columns(2)->statePath('data');
    }

    #[Computed]
    public function report(): array
    {
        $from = CarbonImmutable::parse($this->data['from'] ?? now()->firstOfQuarter())->startOfDay();
        $to = CarbonImmutable::parse($this->data['to'] ?? now())->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return app(ReportBuilder::class)->{$this->builderMethod}($from, $to);
    }

    public function generate(): void
    {
        $this->form->getState();
        unset($this->report);
    }

    protected function setRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->form->fill(['from' => $from, 'to' => $to]);
        unset($this->report);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('thisMonth')->label('This month')
                    ->action(fn () => $this->setRange(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now())),
                Action::make('lastMonth')->label('Last month')
                    ->action(fn () => $this->setRange(CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth(), CarbonImmutable::now()->subMonthNoOverflow()->endOfMonth())),
                Action::make('thisQuarter')->label('This quarter')
                    ->action(fn () => $this->setRange(CarbonImmutable::now()->firstOfQuarter(), CarbonImmutable::now())),
                Action::make('yearToDate')->label('Year to date')
                    ->action(fn () => $this->setRange(CarbonImmutable::now()->startOfYear(), CarbonImmutable::now())),
            ])->label('Quick ranges')->icon('heroicon-m-calendar-days')->button()->color('gray'),
            Action::make('csv')->label('CSV')->icon('heroicon-m-table-cells')->color('gray')->action(fn () => $this->exportCsv()),
            Action::make('pdf')->label('PDF')->icon('heroicon-m-document-arrow-down')->color('gray')->action(fn () => $this->exportPdf()),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $report = $this->report;

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['subtitle'] ?? '']);
            fputcsv($out, []);

            foreach ($report['tiles'] as $tile) {
                fputcsv($out, [$tile['label'], Cell::raw($tile['type'] ?? 'text', $tile['value'])]);
            }
            foreach ($report['sections'] as $section) {
                fputcsv($out, []);
                fputcsv($out, [$section['title']]);
                fputcsv($out, array_map(fn ($c) => $c['label'], $section['columns']));
                foreach ($section['rows'] as $row) {
                    fputcsv($out, $this->csvRow($section['columns'], $row));
                }
                if (! empty($section['total'])) {
                    fputcsv($out, $this->csvRow($section['columns'], $section['total']));
                }
            }
            fclose($out);
        }, $this->downloadName('csv'), ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(): StreamedResponse
    {
        $original = Cell::$currencySymbol;
        Cell::$currencySymbol = 'PHP ';

        try {
            $output = Pdf::loadView('reports.pdf', [
                'report' => $this->report,
                'clinic' => \App\Models\CompanySetting::current(),
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait')->output();
        } finally {
            Cell::$currencySymbol = $original;
        }

        return response()->streamDownload(fn () => print ($output), $this->downloadName('pdf'));
    }

    private function csvRow(array $columns, array $row): array
    {
        $cells = [];
        foreach ($columns as $i => $column) {
            $cells[] = Cell::raw($column['type'] ?? 'text', $row[$i] ?? null);
        }

        return $cells;
    }

    private function downloadName(string $ext): string
    {
        $p = $this->report['period'];

        return Str::slug($this->report['key']."_{$p['from']}_{$p['to']}").'.'.$ext;
    }
}
