<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\RoomResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exam rooms / bays within a branch — used only to pick where an
 * appointment happens (Appointment::room_id). Branches live in
 * {@see LocationResource} instead.
 */
class RoomResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Location::class;

    /**
     * Its own permission key, distinct from 'reference_data' — Setup is
     * principal-only, but branch staff self-serve their own Rooms list.
     */
    protected static function permissionKey(): string
    {
        return 'room';
    }

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Rooms';

    protected static ?int $navigationSort = 24;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Room';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Rooms';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->rooms();
    }

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\Hidden::make('type')->default(Location::TYPE_ROOM),
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Room name shown when assigning appointments to a room, e.g. "Consult Room 1".'),
            Forms\Components\TextInput::make('description')->maxLength(255)
                ->helperText('Optional note to help staff tell similarly named rooms apart.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->limit(50),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRooms::route('/'),
        ];
    }
}
