<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Resources\ConsultationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    protected static ?string $title = 'Consultations';

    protected static ?string $icon = 'heroicon-o-clipboard-document-check';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->consultations()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('consult_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('consult_date')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('provider.name')->label('Vet')->toggleable(),
                Tables\Columns\TextColumn::make('reason.reason')->label('Reason')->toggleable(),
                Tables\Columns\TextColumn::make('consult_diagnosis')->label('Diagnosis')->limit(40)->wrap(),
                Tables\Columns\TextColumn::make('total_inc_tax')->label('Total')->money('PHP')->alignEnd(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'open', 'info' => 'closed', 'success' => 'finalized',
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open', 'closed' => 'Closed', 'finalized' => 'Finalized',
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('new')
                    ->label('New consultation')
                    ->icon('heroicon-m-plus')
                    ->url(fn () => ConsultationResource::getUrl('create'))
                    ->visible(fn () => auth()->user()?->can('create_consultation')),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Model $record) => ConsultationResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn () => auth()->user()?->can('view_consultation')),
            ]);
    }
}
