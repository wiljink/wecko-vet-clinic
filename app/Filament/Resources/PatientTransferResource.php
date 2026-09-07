<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PatientTransferResource\Pages;
use App\Models\PatientTransfer;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatientTransferResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = PatientTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Clients & Patients';

    protected static ?string $navigationLabel = 'Patient Transfers';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false; // transfers are created from the Patient row action
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transferred_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable(),
                Tables\Columns\TextColumn::make('fromClient.full_name')->label('From owner'),
                Tables\Columns\TextColumn::make('toClient.full_name')->label('To owner'),
                Tables\Columns\TextColumn::make('transferredBy.name')->label('By')->toggleable(),
                Tables\Columns\TextColumn::make('reason')->limit(50)->wrap(),
            ])
            ->defaultSort('transferred_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('transferred_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('transferred_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('transferred_at', '<=', $d))),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatientTransfers::route('/'),
        ];
    }
}
