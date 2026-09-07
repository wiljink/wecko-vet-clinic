<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\SuburbPostcodeResource\Pages;
use App\Models\SuburbPostcode;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class SuburbPostcodeResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = SuburbPostcode::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Suburbs & Postcodes';

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'suburb';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\Select::make('state_id')->relationship('state', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('suburb')->required()->maxLength(255),
            Forms\Components\TextInput::make('postcode')->maxLength(10),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('suburb')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('postcode')->searchable(),
            Tables\Columns\TextColumn::make('state.name')->label('State')->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSuburbPostcodes::route('/'),
        ];
    }
}
