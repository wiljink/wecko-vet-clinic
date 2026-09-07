<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\InventoryAdjustmentResource\Pages;
use App\Models\InventoryAdjustment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryAdjustmentResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = InventoryAdjustment::class;

    protected static string $permissionKey = 'inventory_adjustment';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory Adjustments';

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('reference')->disabled()->dehydrated(false)->placeholder('Auto'),
                Forms\Components\DatePicker::make('adjustment_date')->default(now())->required(),
                Forms\Components\Placeholder::make('status')->content(fn (?InventoryAdjustment $record) => ucfirst($record?->status ?? 'draft')),
                Forms\Components\TextInput::make('remarks')->label('Reason for adjustment')->columnSpanFull(),
            ]),
            Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                ->disabled(fn (?InventoryAdjustment $record) => $record?->isPosted())
                ->schema([
                    Forms\Components\Select::make('product_id')->relationship('product', 'name')->required()->searchable(),
                    Forms\Components\TextInput::make('qty_delta')->numeric()->required()
                        ->helperText('Positive to add stock, negative to remove.'),
                    Forms\Components\DatePicker::make('use_by_on')->label('Use-by date'),
                    Forms\Components\TextInput::make('reason'),
                ])->columns(4)->addActionLabel('Add line')->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('adjustment_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('remarks')->limit(50),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Lines')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'posted']),
            ])
            ->defaultSort('adjustment_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (InventoryAdjustment $r) => ! $r->isPosted()),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-m-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn (InventoryAdjustment $r) => ! $r->isPosted() && $r->items()->exists())
                    ->action(function (InventoryAdjustment $r) {
                        $r->post();
                        Notification::make()->title("Adjustment {$r->reference} posted")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }
}
