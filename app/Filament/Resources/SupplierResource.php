<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Supplier::class;

    protected static string $permissionKey = 'supplier';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()
                ->helperText('Displayed throughout purchase orders, receipts and returns for this supplier.'),
            Forms\Components\TextInput::make('contact_name')
                ->helperText('Primary person to contact about orders and deliveries.'),
            Forms\Components\TextInput::make('email')->email()
                ->helperText('Used when purchase orders are emailed to the supplier.'),
            Forms\Components\TextInput::make('phone')->tel()
                ->helperText('Contact number for chasing orders and deliveries.'),
            Forms\Components\TextInput::make('account_no')->label('Account #')
                ->helperText('Your account number with this supplier, printed on purchase orders.'),
            Forms\Components\Toggle::make('is_active')->default(true)
                ->helperText('Inactive suppliers are hidden when creating new orders or products.'),
            Forms\Components\Textarea::make('address')->columnSpanFull()
                ->helperText('Supplier\'s postal address, printed on purchase orders.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email')->toggleable(),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label('Products')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
