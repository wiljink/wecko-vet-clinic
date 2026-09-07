<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\VaccinationResource\Pages;
use App\Models\Vaccination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VaccinationResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Vaccination::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Consultations';

    protected static ?string $navigationLabel = 'Vaccination Register';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('patient_id')->relationship('patient', 'name')->searchable()->required(),
            Forms\Components\Select::make('product_id')->label('Vaccine')
                ->relationship('product', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('kind', 'vaccine'))
                ->searchable(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('batch_no'),
            Forms\Components\DatePicker::make('given_on')->default(now())->required(),
            Forms\Components\Select::make('provider_id')->relationship('provider', 'name')->searchable(),
            Forms\Components\DatePicker::make('booster_due_on'),
            Forms\Components\Textarea::make('protection')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('given_on')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.client.full_name')->label('Owner')->toggleable(),
                Tables\Columns\TextColumn::make('name')->label('Vaccine')->searchable(),
                Tables\Columns\TextColumn::make('batch_no')->label('Batch')->toggleable(),
                Tables\Columns\TextColumn::make('booster_due_on')->date('d M Y')->label('Booster due')->sortable()
                    ->color(fn (Vaccination $r) => $r->booster_due_on && $r->booster_due_on->isPast() ? 'danger' : null),
                Tables\Columns\IconColumn::make('certificate_printed')->boolean()->label('Cert')->toggleable(),
            ])
            ->defaultSort('given_on', 'desc')
            ->filters([
                Tables\Filters\Filter::make('booster_due')->label('Booster overdue')
                    ->query(fn ($query) => $query->whereDate('booster_due_on', '<', now())),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaccinations::route('/'),
            'create' => Pages\CreateVaccination::route('/create'),
            'edit' => Pages\EditVaccination::route('/{record}/edit'),
        ];
    }
}
