<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ClientsOverview;
use App\Filament\Widgets\MonthlySalesChart;
use App\Filament\Widgets\NewClientsChart;
use App\Filament\Widgets\RevisitClientsChart;
use App\Filament\Widgets\SalesPaymentsOverview;
use App\Filament\Widgets\ServicesDistributionChart;
use App\Filament\Widgets\TopBilledClientsChart;
use App\Filament\Widgets\TopSellingItemsChart;
use App\Support\LocationContext;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('location_id')->label('Branch')
                ->options(fn () => LocationContext::accessibleOptions())
                ->placeholder('All Branches')
                ->visible(fn () => LocationContext::canSwitch())
                ->live()
                ->afterStateUpdated(fn ($state) => LocationContext::setActive($state ? (int) $state : null)),
            DatePicker::make('from')->default(now()->startOfYear())->native(false)->closeOnDateSelection()
                ->helperText('Applies to Services Distribution, Top Selling Items and Top Billed Clients.'),
            DatePicker::make('to')->default(now())->native(false)->closeOnDateSelection(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')->label('Reset')->icon('heroicon-m-arrow-path')->color('gray')
                ->action(function () {
                    LocationContext::setActive(null);
                    $this->getFiltersForm()->fill([
                        'location_id' => null,
                        'from' => now()->startOfYear(),
                        'to' => now(),
                    ]);
                }),
        ];
    }

    public function getWidgets(): array
    {
        return [
            SalesPaymentsOverview::class,
            ClientsOverview::class,
            MonthlySalesChart::class,
            ServicesDistributionChart::class,
            NewClientsChart::class,
            RevisitClientsChart::class,
            TopSellingItemsChart::class,
            TopBilledClientsChart::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}
