<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\StockTransferResource\Pages;
use App\Models\StockTransfer;
use App\Support\LocationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = StockTransfer::class;

    protected static string $permissionKey = 'stock_transfer';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Transfers';

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('reference')->disabled()->dehydrated(false)->placeholder('Auto')
                    ->helperText('Assigned automatically once the transfer is saved.'),
                Forms\Components\DatePicker::make('transfer_date')->default(now())->required()
                    ->helperText('Date the stock is moving.'),
                Forms\Components\Placeholder::make('status')->content(fn (?StockTransfer $record) => ucfirst($record?->status ?? 'draft')),
                Forms\Components\Select::make('from_location_id')->label('From branch')
                    ->options(fn () => LocationContext::accessibleOptions())
                    ->default(fn () => LocationContext::activeId())->required()
                    ->helperText('Branch the stock is leaving.'),
                Forms\Components\Select::make('to_location_id')->label('To branch')
                    ->options(fn () => LocationContext::accessibleOptions())->required()
                    ->different('from_location_id')
                    ->helperText('Branch receiving the stock.'),
                Forms\Components\TextInput::make('notes')->columnSpanFull()
                    ->helperText('Optional note, e.g. reason for the transfer.'),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?StockTransfer $record) => $record?->isPosted())
                ->schema([
                    Forms\Components\Select::make('product_id')->relationship('product', 'name')->required()->searchable()
                        ->helperText('Product being transferred.'),
                    Forms\Components\TextInput::make('qty')->numeric()->required()->minValue(0.01)
                        ->helperText('Quantity to move.'),
                ])->columns(2)->addActionLabel('Add line')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('transfer_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('fromLocation.name')->label('From'),
                Tables\Columns\TextColumn::make('toLocation.name')->label('To'),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Lines')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'posted']),
            ])
            ->defaultSort('transfer_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (StockTransfer $r) => ! $r->isPosted()),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-m-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn (StockTransfer $r) => ! $r->isPosted() && $r->items()->exists())
                    ->action(function (StockTransfer $r) {
                        $r->post();
                        Notification::make()->title("Transfer {$r->reference} posted")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
