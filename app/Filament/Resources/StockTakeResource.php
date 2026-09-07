<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\StockTakeResource\Pages;
use App\Models\StockTake;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockTakeResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = StockTake::class;

    protected static string $permissionKey = 'stock_take';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Takes';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('reference')->disabled()->dehydrated(false)->placeholder('Auto'),
                Forms\Components\DatePicker::make('take_date')->default(now())->required(),
                Forms\Components\Placeholder::make('status')->content(fn (?StockTake $record) => ucfirst($record?->status ?? 'open')),
                Forms\Components\TextInput::make('notes')->columnSpanFull(),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?StockTake $record) => $record?->isPosted())
                ->schema([
                    Forms\Components\Select::make('product_id')->relationship('product', 'name')->required()->searchable()->distinct(),
                    Forms\Components\TextInput::make('system_qty')->numeric()->disabled()->dehydrated(),
                    Forms\Components\TextInput::make('counted_qty')->numeric()
                        ->helperText('Leave blank for items you did not count — a blank is never treated as zero.'),
                ])->columns(3)->addActionLabel('Add product')->defaultItems(0)
                ->itemLabel(fn (array $state) => \App\Models\Product::find($state['product_id'] ?? null)?->name),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('take_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Lines')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['warning' => 'open', 'success' => 'posted']),
                Tables\Columns\TextColumn::make('created_at')->date('d M Y')->toggleable(),
            ])
            ->defaultSort('take_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (StockTake $r) => ! $r->isPosted()),
                Tables\Actions\Action::make('loadAll')
                    ->label('Load all products')->icon('heroicon-m-arrow-down-tray')->color('gray')
                    ->visible(fn (StockTake $r) => ! $r->isPosted())
                    ->action(fn (StockTake $r) => $r->loadAllProducts()),
                Tables\Actions\Action::make('sheets')
                    ->label('Count sheet')->icon('heroicon-m-printer')->color('gray')
                    ->url(fn (StockTake $r) => route('stock-takes.sheet', $r))->openUrlInNewTab()
                    ->visible(fn () => \Illuminate\Support\Facades\Route::has('stock-takes.sheet')),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-m-check-badge')->color('success')->requiresConfirmation()
                    ->modalDescription('Posting writes a stock adjustment for every counted line whose count differs from the system quantity.')
                    ->visible(fn (StockTake $r) => ! $r->isPosted() && $r->items()->exists())
                    ->action(function (StockTake $r) {
                        abort_unless(auth()->user()->can('post_stock_take'), 403);
                        $r->post();
                        Notification::make()->title("Stock take {$r->reference} posted")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTakes::route('/'),
            'create' => Pages\CreateStockTake::route('/create'),
            'edit' => Pages\EditStockTake::route('/{record}/edit'),
        ];
    }
}
