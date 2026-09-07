<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\PatientReminderTypeResource\Pages;
use App\Models\PatientReminderType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class PatientReminderTypeResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = PatientReminderType::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Patient Reminder Types';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('category')->required()->default('other')
                ->options(['vaccination' => 'Vaccination', 'desexing' => 'Desexing', 'other' => 'Other']),
            Forms\Components\Fieldset::make('Interval until next due')->schema([
                Forms\Components\TextInput::make('period_years')->numeric()->default(0)->required(),
                Forms\Components\TextInput::make('period_months')->numeric()->default(0)->required(),
                Forms\Components\TextInput::make('period_days')->numeric()->default(0)->required(),
            ])->columns(3),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->badge(),
            Tables\Columns\TextColumn::make('interval')->label('Interval')
                ->state(fn (\App\Models\PatientReminderType $r) => trim(collect([
                    $r->period_years ? $r->period_years.'y' : null,
                    $r->period_months ? $r->period_months.'m' : null,
                    $r->period_days ? $r->period_days.'d' : null,
                ])->filter()->implode(' ')) ?: '—'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePatientReminderTypes::route('/'),
        ];
    }
}
