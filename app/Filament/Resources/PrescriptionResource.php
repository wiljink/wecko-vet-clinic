<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PrescriptionResource\Pages;
use App\Models\Prescription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrescriptionResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Prescription::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';

    protected static ?string $navigationGroup = 'Consultations';

    protected static ?string $navigationLabel = 'Prescriptions';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('patient_id')->relationship('patient', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('drug_name')->required(),
            Forms\Components\Select::make('regime_id')->relationship('regime', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('dosage'),
            Forms\Components\TextInput::make('quantity')->numeric(),
            Forms\Components\TextInput::make('dispensing_fee')->numeric()->prefix('₱'),
            Forms\Components\DatePicker::make('start_on')->default(now()),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('start_on')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('drug_name')->searchable(),
                Tables\Columns\TextColumn::make('dosage'),
                Tables\Columns\TextColumn::make('quantity')->numeric(),
                Tables\Columns\TextColumn::make('regime.name')->label('Regime')->toggleable(),
            ])
            ->defaultSort('start_on', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrescriptions::route('/'),
            'create' => Pages\CreatePrescription::route('/create'),
            'edit' => Pages\EditPrescription::route('/{record}/edit'),
        ];
    }
}
