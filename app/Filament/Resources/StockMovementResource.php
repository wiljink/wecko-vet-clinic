<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = StockMovement::class;

    protected static string $permissionKey = 'stock_movement';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Ledger';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false; // movements are written by workflows, never by hand
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('moved_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state) => StockMovement::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('qty_change')->label('Qty')->numeric()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('balance_after')->label('Balance')->numeric(),
                Tables\Columns\TextColumn::make('unit_cost_ex_tax')->label('Unit cost')->money('PHP')->toggleable(),
                Tables\Columns\TextColumn::make('batch_no')->label('Batch')->toggleable(),
                Tables\Columns\TextColumn::make('reason')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('createdBy.name')->label('By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('moved_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')->relationship('product', 'name')->searchable()->label('Product'),
                Tables\Filters\SelectFilter::make('type')->options(StockMovement::TYPES),
                Tables\Filters\Filter::make('moved_at')->form([
                    \Filament\Forms\Components\DatePicker::make('from'),
                    \Filament\Forms\Components\DatePicker::make('until'),
                ])->query(fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
