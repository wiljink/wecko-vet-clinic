<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Product::class;

    protected static string $permissionKey = 'product';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /** The product kind this resource manages. */
    protected static string $kind = 'product';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', static::$kind);
    }

    public static function getModelLabel(): string
    {
        return Product::KINDS[static::$kind];
    }

    public static function getPluralModelLabel(): string
    {
        return Product::KINDS[static::$kind].'s';
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function form(Form $form): Form
    {
        $tracksStock = static::$kind !== 'service';
        $isVaccine = static::$kind === 'vaccine';

        return $form->schema([
            Forms\Components\Hidden::make('kind')->default(static::$kind),

            Forms\Components\Section::make('Details')->columns(3)->schema([
                Forms\Components\TextInput::make('name')->required()->columnSpan(2)
                    ->helperText('Shown to staff and clients wherever this item is selected, invoiced or printed.'),
                Forms\Components\TextInput::make('code')->helperText('Auto-generated if left blank.'),
                Forms\Components\TextInput::make('description')->label('Title / description')->columnSpan(2)
                    ->helperText('Optional longer title shown on invoices and receipts alongside the name.'),
                Forms\Components\TextInput::make('barcode')->visible($tracksStock)
                    ->autocomplete(false)
                    ->helperText('Scan or type. Leave blank to keep the current value.')
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('generateBarcode')
                            ->icon('heroicon-m-sparkles')->label('Generate')
                            ->action(fn (Forms\Set $set) => $set('barcode', \App\Support\Barcode::generate())),
                    ),
                Forms\Components\Select::make('group_id')->relationship('group', 'name')
                    ->searchable()->preload()
                    ->helperText('Groups this item for reporting; also limits which item types it can apply to.')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\Select::make('applies_to')->options(['product' => 'Products', 'service' => 'Services', 'both' => 'Both'])->default('both'),
                    ]),
                Forms\Components\Select::make('animal_size')->options([
                    'Small Animal' => 'Small Animal', 'Large Animal' => 'Large Animal',
                    'Small and Large Animal' => 'Small and Large Animal',
                ])->helperText('Restricts which patients this item is offered for, based on size category.'),
                Forms\Components\Select::make('list_this_product')->label('Show on')->required()->default('both')
                    ->options(['none' => 'Nowhere', 'consult' => 'Consultations', 'otc' => 'Counter sales', 'both' => 'Both'])
                    ->helperText('Controls where staff can select this item — consultations, counter sales, or both.'),
            ]),

            Forms\Components\Section::make('Pricing')->columns(4)->schema([
                Forms\Components\TextInput::make('tax_rate')->numeric()->suffix('%')->default(12)->required()
                    ->helperText('VAT percentage applied when calculating the tax-inclusive sell price.'),
                Forms\Components\TextInput::make('unit_cost_ex_tax')->label('Cost (ex tax)')->numeric()->prefix('₱')->default(0)->visible($tracksStock)
                    ->helperText('What you pay the supplier, excluding tax; used to calculate margin.'),
                Forms\Components\TextInput::make('mark_up')->numeric()->suffix('%')->default(0)
                    ->helperText('Percentage added to cost as a guide for setting the sell price; does not change it automatically.'),
                Forms\Components\TextInput::make('sell_price_ex_tax')->label('Sell (ex tax)')->numeric()->prefix('₱')->default(0)->required()
                    ->helperText('Tax-inclusive price is calculated on save.'),
                Forms\Components\TextInput::make('dispense_fee')->numeric()->prefix('₱')->default(0)
                    ->helperText('Flat fee added on top of the sell price when this item is dispensed.'),
                Forms\Components\Toggle::make('dispense_fee_always')->label('Always add dispense fee')
                    ->helperText('Adds the dispense fee automatically every time this item is sold, without prompting staff.'),
                Forms\Components\Toggle::make('discountable')->default(true)
                    ->helperText('Allows standing client discounts and promotions to apply to this item.'),
                Forms\Components\Toggle::make('print_label')->label('Prompt to print a label')
                    ->helperText('Asks staff to print a dispensing label whenever this item is sold.'),
            ]),

            Forms\Components\Section::make('Stock control')->columns(4)->visible($tracksStock)->schema([
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()
                    ->helperText('Default supplier used when auto-generating purchase orders for this item.')
                    ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
                Forms\Components\TextInput::make('pack_qty')->numeric()->default(1)
                    ->helperText('Units per supplier pack; used to convert ordered packs into stock quantity on receipt.'),
                Forms\Components\TextInput::make('reorder_level')->numeric()->default(0)
                    ->helperText('Auto-order is raised when qty on hand drops below this.'),
                Forms\Components\TextInput::make('max_holding')->numeric()->default(0)
                    ->helperText('Auto-order tops the shelf back up to here.'),
                Forms\Components\TextInput::make('qty_on_hand')->numeric()->disabled()->dehydrated(false)
                    ->helperText('Adjusted via stock receipts, takes, adjustments and sales.'),
                Forms\Components\Toggle::make('has_expiry')->label('Track expiry / batch dates')
                    ->helperText('Requires a batch number and expiry date whenever stock of this item is received.'),
            ]),

            Forms\Components\Section::make('Vaccine')->columns(3)->visible($isVaccine)->schema([
                Forms\Components\Textarea::make('protection')->columnSpanFull()
                    ->helperText('e.g. F4 — FELINE RHINOTRACHEITIS, CALICIVIRUS, PANLEUCOPENIA & CHLAMYDIA'),
                Forms\Components\Fieldset::make('Booster due after')->schema([
                    Forms\Components\TextInput::make('booster_years')->numeric()->default(0)
                        ->helperText('Years after this vaccination before a booster reminder is due.'),
                    Forms\Components\TextInput::make('booster_months')->numeric()->default(0)
                        ->helperText('Months after this vaccination before a booster reminder is due.'),
                    Forms\Components\TextInput::make('booster_days')->numeric()->default(0)
                        ->helperText('Days after this vaccination before a booster reminder is due.'),
                ])->columns(3),
                Forms\Components\Toggle::make('prints_certificate')->label('Print a vaccination certificate')
                    ->helperText('Prompts staff to print a vaccination certificate when this item is administered.'),
                Forms\Components\Select::make('certificate_template_id')->label('Certificate template')
                    ->relationship('certificateTemplate', 'name', fn (Builder $query) => $query->where('type', 'certificate'))
                    ->helperText('Template used to generate the printed vaccination certificate.'),
                Forms\Components\TextInput::make('next_vaccination_note')
                    ->helperText('Free-text note about the next vaccination due, printed on the certificate.'),
            ]),

            Forms\Components\Section::make('Dispensing & reminders')->columns(2)->schema([
                Forms\Components\Select::make('regime_id')->label('Default drug regime')
                    ->relationship('regime', 'name')->searchable()->preload()->visible(static::$kind === 'product')
                    ->helperText('Drug regime automatically attached to the patient record when this item is dispensed.'),
                Forms\Components\Select::make('patient_reminder_type_id')->label('Raises reminder')
                    ->relationship('reminderType', 'name')->searchable()->preload()
                    ->helperText('Type of reminder automatically raised on the patient record after this item is used.'),
                Forms\Components\Textarea::make('home_care_note')->columnSpanFull()
                    ->helperText('Aftercare instructions shown to staff and printed for the client.'),
                Forms\Components\Toggle::make('is_active')->default(true)
                    ->helperText('Inactive items are hidden from selection lists but kept for historical records.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tracksStock = static::$kind !== 'service';

        return $table
            ->columns(array_filter([
                Tables\Columns\TextColumn::make('code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('barcode')->searchable()->toggleable()->fontFamily('mono')
                    ->placeholder('—')->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('group.name')->label('Group')->badge()->sortable(),
                Tables\Columns\TextColumn::make('sell_price_inc_tax')->label('Price (inc tax)')->money('PHP')->sortable(),
                $tracksStock ? Tables\Columns\TextColumn::make('qty_on_hand')->label('On hand')->numeric()->sortable()
                    ->color(fn (Product $r) => $r->reorder_level > 0 && $r->qty_on_hand < $r->reorder_level ? 'danger' : null) : null,
                $tracksStock ? Tables\Columns\TextColumn::make('reorder_level')->label('Reorder')->numeric()->toggleable(isToggledHiddenByDefault: true) : null,
                Tables\Columns\TextColumn::make('list_this_product')->label('Shown on')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ]))
            ->defaultSort('name')
            ->filters(array_filter([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
                Tables\Filters\SelectFilter::make('group_id')->relationship('group', 'name')->label('Group'),
                $tracksStock ? Tables\Filters\Filter::make('below_reorder')->label('Below reorder level')
                    ->query(fn (Builder $q) => $q->belowReorder()) : null,
            ]))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('printLabel')->label('Label')->icon('heroicon-m-tag')->color('gray')
                    ->visible(fn (Product $r) => (bool) $r->barcode)
                    ->url(fn (Product $r) => route('products.labels', ['ids' => $r->id, 'per' => 1]), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('printLabels')->label('Print barcode labels')->icon('heroicon-m-tag')
                        ->action(fn ($records) => redirect()->route('products.labels', [
                            'ids' => $records->pluck('id')->implode(','),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
