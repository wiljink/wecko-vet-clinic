<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\ColourResource\Pages;
use App\Models\Colour;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ColourResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Colour::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Patient Colours';

    protected static ?int $navigationSort = 17;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Coat/fur colour option offered when recording a patient\'s profile.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageColours::route('/'),
        ];
    }
}
