<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\SpeciesResource\Pages;
use App\Models\Species;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class SpeciesResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Species::class;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = 'Species & Breeds';

    protected static ?int $navigationSort = 16;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Species option offered when registering a patient, e.g. Canine, Feline.'),
            Forms\Components\Select::make('size')->options(['Small Animal' => 'Small Animal', 'Large Animal' => 'Large Animal', 'Small and Large Animal' => 'Small and Large Animal'])
                ->helperText('Size classification used for dosing guidance and consult fee calculations.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('size')->badge(),
            Tables\Columns\TextColumn::make('breeds_count')->counts('breeds')->label('Breeds')->badge(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSpecies::route('/'),
        ];
    }
}
