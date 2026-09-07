<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class LocationResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Locations / Rooms';

    protected static ?int $navigationSort = 23;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('description')->maxLength(255),
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
            'index' => Pages\ManageLocations::route('/'),
        ];
    }
}
