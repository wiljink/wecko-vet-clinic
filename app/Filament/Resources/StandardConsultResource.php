<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\StandardConsultResource\Pages;
use App\Models\Product;
use App\Models\StandardConsult;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StandardConsultResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = StandardConsult::class;

    protected static string $permissionKey = 'standard_consult';

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Consultations';

    protected static ?string $navigationLabel = 'Standard Consults';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('name')->required()
                    ->helperText('Name staff will see when picking this template from "Apply standard consult".'),
                Forms\Components\Select::make('appointment_reason_id')->label('For reason')
                    ->relationship('reason', 'reason')->searchable()->preload()
                    ->helperText('Optional — links this template to a specific visit reason for easier lookup.'),
                Forms\Components\Toggle::make('is_active')->default(true)
                    ->helperText('Inactive templates no longer appear in the "Apply standard consult" picker.'),
            ]),
            Forms\Components\Textarea::make('notes')
                ->helperText('Internal notes on when to use this template; not shown to clients.'),
            Forms\Components\Repeater::make('items')->relationship()->schema([
                Forms\Components\Select::make('kind')->options([
                    'service' => 'Service', 'drug' => 'Drug', 'vaccination' => 'Vaccination',
                ])->default('service')->required()->live()
                    ->helperText('What this item is; determines which product list is offered below.'),
                Forms\Components\Select::make('product_id')->label('Item')->required()->searchable()
                    ->options(fn (Forms\Get $get) => Product::query()
                        ->when($get('kind') === 'service', fn ($q) => $q->where('kind', 'service'))
                        ->when($get('kind') === 'drug', fn ($q) => $q->where('kind', 'product'))
                        ->when($get('kind') === 'vaccination', fn ($q) => $q->where('kind', 'vaccine'))
                        ->orderBy('name')->pluck('name', 'id'))
                    ->helperText('The specific service, drug or vaccine to add whenever this template is applied.'),
                Forms\Components\TextInput::make('qty')->numeric()->default(1)->required()
                    ->helperText('Default quantity added to the consult when this template is applied.'),
            ])->columns(3)->addActionLabel('Add item'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reason.reason')->label('For reason'),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStandardConsults::route('/'),
            'create' => Pages\CreateStandardConsult::route('/create'),
            'edit' => Pages\EditStandardConsult::route('/{record}/edit'),
        ];
    }
}
