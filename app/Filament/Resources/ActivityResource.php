<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ActivityResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    use HasResourcePermissions;

    protected static string $permissionKey = 'activity';

    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i:s')->sortable()->label('When'),
                Tables\Columns\TextColumn::make('causer.name')->label('User')->default('system')->searchable(),
                Tables\Columns\TextColumn::make('log_name')->label('Type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('event')->badge()->colors([
                    'success' => 'created', 'warning' => 'updated', 'danger' => 'deleted',
                ]),
                Tables\Columns\TextColumn::make('description')->wrap(),
                Tables\Columns\TextColumn::make('subject_type')->label('Record')
                    ->formatStateUsing(fn (?string $state, Activity $r) => $state ? class_basename($state).' #'.$r->subject_id : '—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->all()),
                Tables\Filters\SelectFilter::make('event')->options([
                    'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('changes')->icon('heroicon-m-eye')
                    ->modalContent(fn (Activity $r) => view('filament.modals.activity-changes', ['activity' => $r]))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
