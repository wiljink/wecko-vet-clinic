<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\StateResource\Pages;
use App\Models\State;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class StateResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'States / Provinces';

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('State/province name available when entering a client or suburb address.'),
            Forms\Components\TextInput::make('code')->maxLength(10)
                ->helperText('Short abbreviation used on printed addresses and labels.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('suburb_postcodes_count')->counts('suburbPostcodes')->label('Suburbs')->badge(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStates::route('/'),
        ];
    }
}
