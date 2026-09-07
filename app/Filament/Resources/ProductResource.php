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
                Forms\Components\TextInput::make('name')->required()->columnSpan(2),
                Forms\Components\TextInput::make('code')->helperText('Auto-generated if left blank.'),
                Forms\Components\TextInput::make('description')->label('Title / description')->columnSpan(2),
                Forms\Components\TextInput::make('barcode')->visible($tracksStock),
                Forms\Components\Select::make('group_id')->relationship('group', 'name')
                    ->searchable()->preload()->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\Select::make('applies_to')->options(['product' => 'Products', 'service' => 'Services', 'both' => 'Both'])->default('both'),
                    ]),
                Forms\Components\Select::make('animal_size')->options([
                    'Small Animal' => 'Small Animal', 'Large Animal' => 'Large Animal',
                    'Small and Large Animal' => 'Small and Large Animal',
                ]),
                Forms\Components\Select::make('list_this_product')->label('Show on')->required()->default('both')
                    ->options(['none' => 'Nowhere', 'consult' => 'Consultations', 'otc' => 'Counter sales', 'both' => 'Both']),
            ]),

            Forms\Components\Section::make('Pricing')->columns(4)->schema([
                Forms\Components\TextInput::make('tax_rate')->numeric()->suffix('%')->default(12)->required(),
                Forms\Components\TextInput::make('unit_cost_ex_tax')->label('Cost (ex tax)')->numeric()->prefix('₱')->default(0)->visible($tracksStock),
                Forms\Components\TextInput::make('mark_up')->numeric()->suffix('%')->default(0),
                Forms\Components\TextInput::make('sell_price_ex_tax')->label('Sell (ex tax)')->numeric()->prefix('₱')->default(0)->required()
                    ->helperText('Tax-inclusive price is calculated on save.'),
                Forms\Components\TextInput::make('dispense_fee')->numeric()->prefix('₱')->default(0),
                Forms\Components\Toggle::make('dispense_fee_always')->label('Always add dispense fee'),
                Forms\Components\Toggle::make('discountable')->default(true),
                Forms\Components\Toggle::make('print_label')->label('Prompt to print a label'),
            ]),

            Forms\Components\Section::make('Stock control')->columns(4)->visible($tracksStock)->schema([
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()
                    ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
                Forms\Components\TextInput::make('pack_qty')->numeric()->default(1),
                Forms\Components\TextInput::make('reorder_level')->numeric()->default(0)
                    ->helperText('Auto-order is raised when qty on hand drops below this.'),
                Forms\Components\TextInput::make('max_holding')->numeric()->default(0)
                    ->helperText('Auto-order tops the shelf back up to here.'),
                Forms\Components\TextInput::make('qty_on_hand')->numeric()->disabled()->dehydrated(false)
                    ->helperText('Adjusted via stock receipts, takes, adjustments and sales.'),
                Forms\Components\Toggle::make('has_expiry')->label('Track expiry / batch dates'),
            ]),

            Forms\Components\Section::make('Vaccine')->columns(3)->visible($isVaccine)->schema([
                Forms\Components\Textarea::make('protection')->columnSpanFull()
                    ->helperText('e.g. F4 — FELINE RHINOTRACHEITIS, CALICIVIRUS, PANLEUCOPENIA & CHLAMYDIA'),
                Forms\Components\Fieldset::make('Booster due after')->schema([
                    Forms\Components\TextInput::make('booster_years')->numeric()->default(0),
                    Forms\Components\TextInput::make('booster_months')->numeric()->default(0),
                    Forms\Components\TextInput::make('booster_days')->numeric()->default(0),
                ])->columns(3),
                Forms\Components\Toggle::make('prints_certificate')->label('Print a vaccination certificate'),
                Forms\Components\Select::make('certificate_template_id')->label('Certificate template')
                    ->relationship('certificateTemplate', 'name', fn (Builder $query) => $query->where('type', 'certificate')),
                Forms\Components\TextInput::make('next_vaccination_note'),
            ]),

            Forms\Components\Section::make('Dispensing & reminders')->columns(2)->schema([
                Forms\Components\Select::make('regime_id')->label('Default drug regime')
                    ->relationship('regime', 'name')->searchable()->preload()->visible(static::$kind === 'product'),
                Forms\Components\Select::make('patient_reminder_type_id')->label('Raises reminder')
                    ->relationship('reminderType', 'name')->searchable()->preload(),
                Forms\Components\Textarea::make('home_care_note')->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tracksStock = static::$kind !== 'service';

        return $table
            ->columns(array_filter([
                Tables\Columns\TextColumn::make('code')->searchable()->toggleable(),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
