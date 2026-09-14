<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Appointments';

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->appointments()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')->dateTime('d M Y H:i')->label('When')->sortable(),
                Tables\Columns\TextColumn::make('provider.name')->label('Vet')->toggleable(),
                Tables\Columns\TextColumn::make('reason.reason')->label('Reason')->toggleable(),
                Tables\Columns\TextColumn::make('status.name')->label('Status')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('notes')->limit(40)->wrap()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Model $record) => AppointmentResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn () => auth()->user()?->can('view_appointment')),
            ])
            ->paginated([10, 25, 50]);
    }
}
