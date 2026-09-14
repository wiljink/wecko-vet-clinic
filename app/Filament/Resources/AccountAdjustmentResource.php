<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\AccountAdjustmentResource\Pages;
use App\Models\AccountAdjustment;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountAdjustmentResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = AccountAdjustment::class;

    protected static string $permissionKey = 'account_adjustment';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Account Adjustments';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return auth()->user()?->can('make_account_adjustment') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')->required()
                ->relationship('client', 'surname')
                ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->full_name)
                ->searchable(['surname', 'given_name'])->preload()
                ->helperText('The account whose balance this manual adjustment will change.'),
            Forms\Components\Select::make('direction')->required()->options([
                'credit' => 'Credit (reduce what they owe)',
                'debit' => 'Debit (increase what they owe)',
            ])->helperText('Credit lowers the client balance without a payment being received; debit raises it without an invoice being issued.'),
            Forms\Components\TextInput::make('amount')->numeric()->prefix('₱')->required()
                ->helperText('The peso amount by which the balance moves in the direction selected above.'),
            Forms\Components\DatePicker::make('adjusted_on')->default(now())->required()
                ->helperText('The date the adjustment is effective from — it affects aging and statements from this date.'),
            Forms\Components\TextInput::make('reason')->required()->columnSpanFull()
                ->placeholder('e.g. Goodwill discount, Clinic-Ware conversion, Bad debt write-off')
                ->helperText('Why the balance is being corrected manually — kept on record for audit and shown against the entry.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable(),
                Tables\Columns\TextColumn::make('adjusted_on')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(['surname']),
                Tables\Columns\TextColumn::make('direction')->badge()->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('amount')->money('PHP'),
                Tables\Columns\TextColumn::make('reason')->limit(50)->wrap(),
                Tables\Columns\TextColumn::make('creator.name')->label('By')->toggleable(),
            ])
            ->defaultSort('adjusted_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')->options(['credit' => 'Credit', 'debit' => 'Debit']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountAdjustments::route('/'),
            'create' => Pages\CreateAccountAdjustment::route('/create'),
        ];
    }
}
