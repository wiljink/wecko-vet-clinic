<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\AppointmentStatusResource\Pages;
use App\Models\AppointmentStatus;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class AppointmentStatusResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = AppointmentStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Appointment Statuses';

    protected static ?int $navigationSort = 27;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Status shown against an appointment as it progresses, e.g. Confirmed, Arrived, Completed.'),
            Forms\Components\TextInput::make('menu_caption')->maxLength(255)
                ->helperText('Shorter text shown on the calendar grid when space is tight.'),
            Forms\Components\ColorPicker::make('color')->required()->default('#64748b')
                ->helperText('Colour used to visually distinguish appointments in this status on the calendar.'),
            Forms\Components\Toggle::make('is_default')->helperText('Applied to new appointments.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\ColorColumn::make('color'),
            Tables\Columns\IconColumn::make('is_default')->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAppointmentStatuses::route('/'),
        ];
    }
}
