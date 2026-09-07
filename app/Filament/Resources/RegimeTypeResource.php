<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\RegimeTypeResource\Pages;
use App\Models\RegimeType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class RegimeTypeResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = RegimeType::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Drug Regimes';

    protected static ?int $navigationSort = 19;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('total_qty_used')->numeric()->default(1)->required()
                ->helperText('Units dispensed per course — auto-fills the quantity on a consult/sale line.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('total_qty_used')->label('Qty / course')->numeric(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRegimeTypes::route('/'),
        ];
    }
}
