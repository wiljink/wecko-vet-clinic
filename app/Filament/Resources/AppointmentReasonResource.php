<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\AppointmentReasonResource\Pages;
use App\Models\AppointmentReason;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class AppointmentReasonResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = AppointmentReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Appointment Reasons';

    protected static ?int $navigationSort = 26;

    protected static ?string $recordTitleAttribute = 'reason';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('code')->maxLength(20)
                ->helperText('Short internal code for this reason, used in reports and quick entry.'),
            Forms\Components\TextInput::make('reason')->required()->maxLength(255)
                ->helperText('Reason text shown to staff when booking an appointment, e.g. Vaccination or Follow-up.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('reason')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAppointmentReasons::route('/'),
        ];
    }
}
