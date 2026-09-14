<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\StockReceiptResource\Pages;
use App\Models\Product;
use App\Models\StockReceipt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockReceiptResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = StockReceipt::class;

    protected static string $permissionKey = 'stock_receipt';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Receipts';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'receipt_no';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('receipt_no')->disabled()->dehydrated(false)->placeholder('Auto')
                    ->helperText('Assigned automatically once the receipt is saved.'),
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->required()->searchable()->preload()->live()
                    ->disabled(fn (?StockReceipt $record) => $record?->isPosted())
                    ->helperText('Supplier this stock is being received from.'),
                Forms\Components\Select::make('inventory_order_id')->label('Against order')
                    ->relationship('order', 'order_no', fn (\Illuminate\Database\Eloquent\Builder $query, Forms\Get $get) => $query
                        ->when($get('supplier_id'), fn ($q, $s) => $q->where('supplier_id', $s))
                        ->whereIn('status', ['placed', 'partially_received']))
                    ->searchable()->helperText('Leave blank for non-ordered stock.'),
                Forms\Components\DatePicker::make('received_date')->default(now())->required()
                    ->helperText('Date the stock physically arrived.'),
                Forms\Components\TextInput::make('supplier_doc_no')->label('Supplier invoice #')
                    ->helperText('Supplier\'s invoice or delivery note number, for matching against their paperwork.'),
                Forms\Components\Placeholder::make('status')->content(fn (?StockReceipt $record) => ucfirst($record?->status ?? 'draft')),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?StockReceipt $record) => $record?->isPosted())
                ->schema([
                    Forms\Components\Select::make('product_id')->label('Product')->required()
                        ->options(fn () => Product::whereIn('kind', ['product', 'vaccine'])->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->live()
                        ->helperText('Selecting a product fills in its current cost and sell price below.')
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($p = Product::find($state)) {
                                $set('unit_cost_ex_tax', $p->unit_cost_ex_tax);
                                $set('sell_price_ex_tax', $p->sell_price_ex_tax);
                            }
                        }),
                    Forms\Components\TextInput::make('qty')->numeric()->required()->default(1)
                        ->helperText('Quantity actually received — may differ from what was ordered.'),
                    Forms\Components\TextInput::make('unit_cost_ex_tax')->numeric()->prefix('₱')->required()
                        ->helperText('Actual cost paid per unit; posting this receipt updates the product\'s cost.'),
                    Forms\Components\TextInput::make('sell_price_ex_tax')->numeric()->prefix('₱')->label('New sell price')
                        ->helperText('Optional — updates the product\'s sell price when this receipt is posted.'),
                    Forms\Components\TextInput::make('batch_no')
                        ->helperText('Batch or lot number, for products where expiry tracking matters.'),
                    Forms\Components\DatePicker::make('expiry_on')
                        ->helperText('Expiry date of this batch, used for labels and expiry alerts.'),
                ])->columns(3)->addActionLabel('Add line')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('order.order_no')->label('Order')->toggleable(),
                Tables\Columns\TextColumn::make('received_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('total_ex_tax')->money('PHP')->label('Total (ex tax)'),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'posted']),
            ])
            ->defaultSort('received_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'posted' => 'Posted']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (StockReceipt $r) => ! $r->isPosted()),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-m-check-badge')->color('success')->requiresConfirmation()
                    ->modalDescription('Posting adds the received quantities to stock and updates cost/sell prices. This cannot be undone.')
                    ->visible(fn (StockReceipt $r) => ! $r->isPosted() && $r->items()->exists())
                    ->action(function (StockReceipt $r) {
                        abort_unless(auth()->user()->can('post_stock_receipt'), 403);
                        $r->post();
                        Notification::make()->title("Receipt {$r->receipt_no} posted")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockReceipts::route('/'),
            'create' => Pages\CreateStockReceipt::route('/create'),
            'edit' => Pages\EditStockReceipt::route('/{record}/edit'),
        ];
    }
}
