<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\CounterSaleResource\Pages;
use App\Models\Client;
use App\Models\CounterSale;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CounterSaleResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = CounterSale::class;

    protected static string $permissionKey = 'counter_sale';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'sale_no';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Toggle::make('walk_in')->label('Walk-in customer')->default(true)->live(),
                Forms\Components\TextInput::make('walk_in_name')->label('Customer name')
                    ->visible(fn (Forms\Get $get) => $get('walk_in')),
                Forms\Components\Select::make('client_id')->label('Client')
                    ->relationship('client', 'surname')
                    ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->full_name)
                    ->searchable(['surname', 'given_name'])->preload()
                    ->visible(fn (Forms\Get $get) => ! $get('walk_in'))
                    ->required(fn (Forms\Get $get) => ! $get('walk_in')),
                Forms\Components\Select::make('provider_id')->relationship('provider', 'name')->searchable()->default(auth()->id()),
                Forms\Components\Select::make('location_id')->relationship('location', 'name')->searchable(),
                Forms\Components\DatePicker::make('sale_date')->default(now())->required(),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?CounterSale $record) => $record?->isCompleted())
                ->schema([
                    Forms\Components\Select::make('kind')->options(['product' => 'Stock item', 'misc' => 'Misc'])
                        ->default('product')->required()->live(),
                    Forms\Components\Select::make('product_id')->label('Product')
                        ->options(fn () => Product::sellable('otc')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->live()->visible(fn (Forms\Get $get) => $get('kind') === 'product')
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($p = Product::find($state)) {
                                $set('description', $p->name);
                                $set('unit_price_ex_tax', $p->sell_price_ex_tax);
                                $set('tax_rate', $p->tax_rate);
                                $set('dispensing_fee', $p->dispense_fee_always ? $p->dispense_fee : 0);
                                $set('regime_id', $p->regime_id);
                            }
                        }),
                    Forms\Components\TextInput::make('description')->required(),
                    Forms\Components\TextInput::make('qty')->numeric()->default(1)->required(),
                    Forms\Components\TextInput::make('unit_price_ex_tax')->label('Unit (ex tax)')->numeric()->prefix('₱')->required(),
                    Forms\Components\TextInput::make('discount_pct')->label('Disc %')->numeric()->default(0),
                    Forms\Components\TextInput::make('tax_rate')->label('Tax %')->numeric()->default(12),
                    Forms\Components\TextInput::make('dispensing_fee')->numeric()->prefix('₱')->default(0),
                    Forms\Components\Select::make('regime_id')->label('Regime')->relationship('regime', 'name')
                        ->searchable()->preload()->visible(fn (Forms\Get $get) => $get('kind') === 'product'),
                ])->columns(4)->addActionLabel('Add item')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sale_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('customer')->label('Customer')
                    ->state(fn (CounterSale $r) => $r->client?->full_name ?? ($r->walk_in_name ?: 'Walk-in')),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items')->badge(),
                Tables\Columns\TextColumn::make('total_inc_tax')->money('PHP')->label('Total'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'open', 'success' => 'completed', 'info' => 'on_account',
                ]),
            ])
            ->defaultSort('sale_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open', 'completed' => 'Completed', 'on_account' => 'On account',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (CounterSale $r) => ! $r->isCompleted()),
                Tables\Actions\Action::make('makePayment')
                    ->label('Make payment')->icon('heroicon-m-banknotes')->color('success')
                    ->visible(fn (CounterSale $r) => ! $r->isCompleted() && $r->items()->exists())
                    ->form([
                        Forms\Components\Placeholder::make('due')->label('Amount due')
                            ->content(fn (CounterSale $r) => '₱'.number_format((float) $r->total_inc_tax, 2)),
                        Forms\Components\Select::make('payment_type')->required()->default('cash')
                            ->options(['cash' => 'Cash', 'credit_card' => 'Credit card', 'eftpos' => 'EFTPOS', 'cheque' => 'Cheque'])
                            ->live(),
                        Forms\Components\TextInput::make('cash_received')->numeric()->prefix('₱')
                            ->visible(fn (Forms\Get $get) => $get('payment_type') === 'cash')
                            ->helperText('Change is calculated automatically.'),
                        Forms\Components\Select::make('card_type_id')->label('Card type')
                            ->options(\App\Models\CardType::pluck('name', 'id'))
                            ->visible(fn (Forms\Get $get) => in_array($get('payment_type'), ['credit_card', 'eftpos'])),
                    ])
                    ->action(function (CounterSale $r, array $data) {
                        abort_unless(auth()->user()->can('process_payment'), 403);
                        [$invoice, $payment] = $r->complete($data);
                        $change = $payment?->change_given ?? 0;
                        Notification::make()
                            ->title("Sale complete — invoice {$invoice->invoice_no}".($change > 0 ? ", change ₱".number_format($change, 2) : ''))
                            ->success()->send();
                    }),
                Tables\Actions\Action::make('closeOnAccount')
                    ->label('Close on account')->icon('heroicon-m-document-text')->color('gray')
                    ->visible(fn (CounterSale $r) => ! $r->isCompleted() && ! $r->walk_in && $r->client_id && $r->items()->exists())
                    ->requiresConfirmation()
                    ->action(function (CounterSale $r) {
                        [$invoice] = $r->complete(null);
                        Notification::make()->title("Charged to account — invoice {$invoice->invoice_no}")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounterSales::route('/'),
            'create' => Pages\CreateCounterSale::route('/create'),
            'edit' => Pages\EditCounterSale::route('/{record}/edit'),
        ];
    }
}
