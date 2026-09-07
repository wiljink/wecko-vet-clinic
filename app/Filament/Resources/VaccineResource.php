<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VaccineResource\Pages;

class VaccineResource extends ProductResource
{
    protected static string $kind = 'vaccine';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaccines::route('/'),
            'create' => Pages\CreateVaccine::route('/create'),
            'edit' => Pages\EditVaccine::route('/{record}/edit'),
        ];
    }
}
