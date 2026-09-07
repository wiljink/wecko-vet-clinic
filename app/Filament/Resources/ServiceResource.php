<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;

class ServiceResource extends ProductResource
{
    protected static string $kind = 'service';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 3;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
