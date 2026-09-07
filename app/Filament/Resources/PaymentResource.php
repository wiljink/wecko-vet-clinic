<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'payment_no';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')->label('Client')->required()
                ->relationship('client', 'surname')
                ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->full_name.' — balance ₱'.number_format($c->currentBalance(), 2))
                ->searchable(['surname', 'given_name'])->preload()->live(),
            Forms\Components\Select::make('payment_type')->required()->default('cash')
                ->options(Payment::TYPES)->live(),
            Forms\Components\TextInput::make('amount')->numeric()->prefix('₱')->required()->live(onBlur: true),
            Forms\Components\TextInput::make('cash_received')->numeric()->prefix('₱')
                ->visible(fn (Forms\Get $get) => $get('payment_type') === 'cash')
                ->helperText('Change is worked out automatically.'),
            Forms\Components\Select::make('card_type_id')->relationship('cardType', 'name')
                ->visible(fn (Forms\Get $get) => in_array($get('payment_type'), ['credit_card', 'eftpos'])),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Toggle::make('is_refund')->helperText('Money out — reduces the account credit.'),
            Forms\Components\CheckboxList::make('apply_to')->label('Apply to invoices')
                ->options(fn (Forms\Get $get) => $get('client_id')
                    ? Invoice::where('client_id', $get('client_id'))->outstanding()->get()
                        ->mapWithKeys(fn (Invoice $i) => [$i->id => "{$i->invoice_no} — ₱".number_format((float) $i->balance, 2).' outstanding'])
                    : [])
                ->visible(fn (Forms\Get $get) => $get('payment_type') !== 'advance_payment' && filled($get('client_id')))
                ->columns(1)->dehydrated(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('received_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(['surname']),
                Tables\Columns\TextColumn::make('payment_type')->badge()
                    ->formatStateUsing(fn (string $state) => Payment::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('amount')->money('PHP')
                    ->color(fn (Payment $r) => $r->is_refund ? 'danger' : null)
                    ->formatStateUsing(fn ($state, Payment $r) => ($r->is_refund ? '-' : '').'₱'.number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('change_given')->money('PHP')->toggleable(),
                Tables\Columns\TextColumn::make('unallocated')->label('Unapplied')->money('PHP')
                    ->state(fn (Payment $r) => $r->unallocated)->toggleable(),
                Tables\Columns\IconColumn::make('banked')->boolean()->toggleable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_type')->options(Payment::TYPES),
                Tables\Filters\TernaryFilter::make('banked'),
                Tables\Filters\TernaryFilter::make('is_refund')->label('Refunds'),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')->icon('heroicon-m-printer')->color('gray')
                    ->url(fn (Payment $r) => route('payments.receipt', $r))->openUrlInNewTab()
                    ->visible(fn () => \Illuminate\Support\Facades\Route::has('payments.receipt')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
        ];
    }
}
