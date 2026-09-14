<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\ConsultationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Every prescription dispensed across all of this client's patients, in one
 * list, so a vet only has to check here instead of each pet's own record.
 */
class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    protected static ?string $title = 'Prescriptions';

    protected static ?string $icon = 'heroicon-o-beaker';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->prescriptions()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_on', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('start_on')->date('d M Y')->label('Started')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable(),
                Tables\Columns\TextColumn::make('drug_name')->label('Drug')->searchable(),
                Tables\Columns\TextColumn::make('dosage')->label('Directions')->wrap(),
                Tables\Columns\TextColumn::make('regime.name')->label('Regime')->toggleable(),
                Tables\Columns\TextColumn::make('quantity')->alignEnd(),
                Tables\Columns\TextColumn::make('notes')->limit(40)->wrap()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('consult')
                    ->label('Consult')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Model $record) => $record->consultation_id
                        ? ConsultationResource::getUrl('edit', ['record' => $record->consultation_id])
                        : null)
                    ->visible(fn (Model $record) => $record->consultation_id && auth()->user()?->can('view_consultation')),
            ])
            ->paginated([10, 25, 50]);
    }
}
