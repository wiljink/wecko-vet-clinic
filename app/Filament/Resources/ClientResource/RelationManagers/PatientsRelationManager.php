<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\PatientResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PatientsRelationManager extends RelationManager
{
    protected static string $relationship = 'patients';

    protected static ?string $title = 'Patients';

    protected static ?string $icon = 'heroicon-o-heart';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')->label('')->circular()->height(32),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('species.name')->label('Species'),
                Tables\Columns\TextColumn::make('breed.name')->label('Breed')->toggleable(),
                Tables\Columns\TextColumn::make('age_label')->label('Age'),
                Tables\Columns\TextColumn::make('gender')->badge(),
                Tables\Columns\TextColumn::make('last_visit_on')->date('d M Y')->label('Last visit'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(fn ($livewire) => PatientResource::getUrl('create', [
                        'client_id' => $livewire->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn ($record) => PatientResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
