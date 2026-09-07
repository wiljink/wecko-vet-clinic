<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\InventoryOrderResource\Pages;
use App\Models\InventoryOrder;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryOrderResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = InventoryOrder::class;

    protected static string $permissionKey = 'inventory_order';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Orders';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'order_no';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('order_no')->disabled()->dehydrated(false)->placeholder('Auto'),
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')
                    ->required()->searchable()->preload()->live(),
                Forms\Components\Select::make('status')->options([
                    'draft' => 'Draft', 'placed' => 'Placed', 'partially_received' => 'Partially received',
                    'received' => 'Received', 'cancelled' => 'Cancelled',
                ])->default('draft')->required(),
                Forms\Components\DatePicker::make('order_date')->default(now())->required(),
                Forms\Components\DatePicker::make('delivery_date'),
                Forms\Components\TextInput::make('total_ex_tax')->prefix('₱')->disabled()->dehydrated(false),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->schema([
                    Forms\Components\Select::make('product_id')->label('Product')->required()
                        ->options(fn (Forms\Get $get) => Product::query()
                            ->whereIn('kind', ['product', 'vaccine'])
                            ->when($get('../../supplier_id'), fn ($q, $s) => $q->where('supplier_id', $s))
                            ->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($p = Product::find($state)) {
                                $set('unit_cost_ex_tax', $p->unit_cost_ex_tax);
                            }
                        }),
                    Forms\Components\TextInput::make('qty_ordered')->numeric()->required()->default(1),
                    Forms\Components\TextInput::make('unit_cost_ex_tax')->numeric()->prefix('₱')->required()->default(0),
                    Forms\Components\Placeholder::make('received')
                        ->content(fn ($record) => $record?->qty_received ?? '0'),
                ])->columns(4)->addActionLabel('Add line')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('order_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('delivery_date')->date('d M Y')->toggleable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Lines')->badge(),
                Tables\Columns\TextColumn::make('total_ex_tax')->money('PHP')->label('Total (ex tax)'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'gray' => 'draft', 'info' => 'placed', 'warning' => 'partially_received',
                    'success' => 'received', 'danger' => 'cancelled',
                ]),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'placed' => 'Placed', 'partially_received' => 'Partially received',
                    'received' => 'Received', 'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('supplier_id')->relationship('supplier', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('place')
                    ->icon('heroicon-m-paper-airplane')
                    ->visible(fn (InventoryOrder $r) => $r->status === 'draft')
                    ->action(fn (InventoryOrder $r) => $r->update(['status' => 'placed'])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('autoReorder')
                    ->label('Auto-reorder')->icon('heroicon-m-sparkles')->color('gray')
                    ->form([
                        Forms\Components\Select::make('supplier_id')->label('Supplier')->required()
                            ->options(Supplier::where('is_active', true)->pluck('name', 'id'))->searchable(),
                    ])
                    ->action(function (array $data) {
                        $order = InventoryOrder::autoFillFor(Supplier::find($data['supplier_id']));

                        if ($order->items()->count() === 0) {
                            $order->delete();
                            Notification::make()->title('Nothing below reorder level for that supplier')->warning()->send();

                            return;
                        }

                        Notification::make()->title("Draft order {$order->order_no} created with {$order->items()->count()} line(s)")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryOrders::route('/'),
            'create' => Pages\CreateInventoryOrder::route('/create'),
            'edit' => Pages\EditInventoryOrder::route('/{record}/edit'),
        ];
    }
}
