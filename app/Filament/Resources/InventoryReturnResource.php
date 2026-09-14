<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\InventoryReturnResource\Pages;
use App\Models\InventoryReturn;
use App\Models\Product;
use App\Support\LocationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryReturnResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = InventoryReturn::class;

    protected static string $permissionKey = 'inventory_return';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory Returns';

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('reference')->disabled()->dehydrated(false)->placeholder('Auto')
                    ->helperText('Assigned automatically once the return is saved.'),
                Forms\Components\Select::make('direction')->required()->live()->default('supplier')
                    ->options(['supplier' => 'Return to supplier', 'customer' => 'Customer return'])
                    ->disabled(fn (?InventoryReturn $record) => $record?->isPosted())
                    ->helperText('Whether this sends stock back to the supplier or takes stock back from a customer.'),
                Forms\Components\DatePicker::make('return_date')->default(now())->required()
                    ->helperText('Date the return is being processed.'),
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()
                    ->visible(fn (Forms\Get $get) => $get('direction') === 'supplier')
                    ->required(fn (Forms\Get $get) => $get('direction') === 'supplier')
                    ->helperText('Supplier the stock is being returned to.'),
                Forms\Components\Select::make('client_id')->relationship('client', 'surname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['surname', 'given_name'])->preload()
                    ->visible(fn (Forms\Get $get) => $get('direction') === 'customer')
                    ->required(fn (Forms\Get $get) => $get('direction') === 'customer')
                    ->helperText('Client returning the item.'),
                Forms\Components\TextInput::make('source_document')->label('Order / invoice #')
                    ->helperText('Original purchase order or sale invoice number this return relates to.'),
                LocationContext::selectField()->helperText('Branch this return applies to.'),
                Forms\Components\TextInput::make('reason')->columnSpanFull()
                    ->helperText('Reason for the return, e.g. wrong item, damaged, or expired.'),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?InventoryReturn $record) => $record?->isPosted())
                ->schema([
                    Forms\Components\Select::make('product_id')->label('Product')->required()->searchable()
                        ->options(fn () => Product::whereIn('kind', ['product', 'vaccine'])->orderBy('name')->pluck('name', 'id'))
                        ->live()
                        ->helperText('Product being returned; for customer returns this fills in the sell price below.')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if (($p = Product::find($state)) && $get('../../direction') === 'customer') {
                                $set('unit_price', $p->sell_price_inc_tax);
                            }
                        }),
                    Forms\Components\TextInput::make('qty')->numeric()->required()->default(1)
                        ->helperText('Quantity being returned.'),
                    Forms\Components\TextInput::make('unit_price')->numeric()->prefix('₱')->default(0)
                        ->helperText('Price used to calculate the refund (customer returns) or credit (supplier returns).'),
                ])->columns(3)->addActionLabel('Add line')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->colors(['warning' => 'supplier', 'info' => 'customer']),
                Tables\Columns\TextColumn::make('party')->label('Party')
                    ->state(fn (InventoryReturn $r) => $r->supplier?->name ?? $r->client?->full_name ?? '—'),
                Tables\Columns\TextColumn::make('return_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('refund_amount')->money('PHP'),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'posted']),
            ])
            ->defaultSort('return_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')->options(['supplier' => 'Supplier', 'customer' => 'Customer']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (InventoryReturn $r) => ! $r->isPosted()),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-m-check-badge')->color('success')->requiresConfirmation()
                    ->modalDescription(fn (InventoryReturn $r) => $r->direction === 'customer'
                        ? 'Stock comes back in and a cash refund is recorded on the client account.'
                        : 'Stock leaves for return to the supplier.')
                    ->visible(fn (InventoryReturn $r) => ! $r->isPosted() && $r->items()->exists())
                    ->action(function (InventoryReturn $r) {
                        abort_unless(auth()->user()->can($r->direction === 'customer' ? 'process_refund' : 'update_inventory_return'), 403);
                        $r->post();
                        Notification::make()->title("Return {$r->reference} posted")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryReturns::route('/'),
            'create' => Pages\CreateInventoryReturn::route('/create'),
            'edit' => Pages\EditInventoryReturn::route('/{record}/edit'),
        ];
    }
}
