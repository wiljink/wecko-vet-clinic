<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Read-only breakdown of this product's on-hand quantity per branch. */
class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stock by branch';

    protected static ?string $icon = 'heroicon-o-building-office';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('qty_on_hand', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('location.name')->label('Branch'),
                Tables\Columns\TextColumn::make('qty_on_hand')->label('On hand')->numeric(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
