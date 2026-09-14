<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\AppointmentLabelResource\Pages;
use App\Models\AppointmentLabel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class AppointmentLabelResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = AppointmentLabel::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Appointment Labels';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Label available when tagging an appointment, e.g. Surgery or Urgent.'),
            Forms\Components\TextInput::make('menu_caption')->maxLength(255)
                ->helperText('Shorter text shown on the label when space is tight on the calendar.'),
            Forms\Components\ColorPicker::make('color')->required()->default('#64748b')
                ->helperText('Colour swatch used to highlight appointments with this label on the calendar.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\ColorColumn::make('color'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAppointmentLabels::route('/'),
        ];
    }
}
