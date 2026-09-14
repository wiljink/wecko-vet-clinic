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
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Room or branch name shown when assigning appointments and stock to a location.'),
            Forms\Components\TextInput::make('code')->maxLength(255)
                ->helperText('Short branch code, e.g. for receipt/invoice numbering across branches. Leave blank for a room.'),
            Forms\Components\TextInput::make('description')->maxLength(255)
                ->helperText('Optional note to help staff tell similarly named locations apart.'),
            Forms\Components\Textarea::make('address')->maxLength(500)
                ->helperText('Street address, for a branch that is a physical clinic site.'),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\Toggle::make('is_main')->label('Main branch')
                ->helperText('The default branch new staff and historical records fall back to. Only one branch can be main.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->badge()->toggleable(),
            Tables\Columns\TextColumn::make('description')->limit(50)->toggleable(),
            Tables\Columns\TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('is_main')->label('Main')->boolean()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLocations::route('/'),
        ];
    }
}
