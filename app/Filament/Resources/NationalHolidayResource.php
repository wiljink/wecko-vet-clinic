<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\NationalHolidayResource\Pages;
use App\Models\NationalHoliday;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class NationalHolidayResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = NationalHoliday::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'National Holidays';

    protected static ?int $navigationSort = 24;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Name of the public holiday, shown on the appointment calendar and staff roster.'),
            Forms\Components\DatePicker::make('holiday_date')->required()
                ->helperText('Date the clinic observes this holiday — used to flag the day as non-working when scheduling.'),
            Forms\Components\TextInput::make('description')->maxLength(255)
                ->helperText('Optional note about the holiday, shown when hovering over it on the calendar.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('holiday_date')->date('d M Y')->sortable(),
            Tables\Columns\TextColumn::make('description')->limit(40),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNationalHolidays::route('/'),
        ];
    }
}
