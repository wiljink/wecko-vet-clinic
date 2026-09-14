<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Every vaccination given across all of this client's patients, in one list.
 */
class VaccinationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vaccinations';

    protected static ?string $title = 'Vaccinations';

    protected static ?string $icon = 'heroicon-o-shield-check';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->vaccinations()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('given_on', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('given_on')->date('d M Y')->label('Given')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Vaccine')->searchable(),
                Tables\Columns\TextColumn::make('batch_no')->label('Batch')->toggleable(),
                Tables\Columns\TextColumn::make('provider.name')->label('Vet')->toggleable(),
                Tables\Columns\TextColumn::make('protection')->toggleable(),
                Tables\Columns\TextColumn::make('booster_due_on')->date('d M Y')->label('Booster due')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('certificate_printed')->label('Cert.')->boolean()->toggleable(),
            ])
            ->actions([])
            ->paginated([10, 25, 50]);
    }
}
