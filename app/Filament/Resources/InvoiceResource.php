<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    public static function canCreate(): bool
    {
        return false; // invoices come from consults and counter sales
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(['surname'])->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->toggleable(),
                Tables\Columns\TextColumn::make('source_type')->label('Source')->badge()
                    ->formatStateUsing(fn (?string $state) => class_basename((string) $state) ?: '—'),
                Tables\Columns\TextColumn::make('total')->money('PHP')->sortable(),
                Tables\Columns\TextColumn::make('balance')->money('PHP')->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'danger' => 'unpaid', 'warning' => 'partial', 'success' => 'paid', 'gray' => 'void',
                ]),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'void' => 'Void',
                ]),
                Tables\Filters\Filter::make('outstanding')->label('Outstanding only')
                    ->query(fn ($query) => $query->outstanding()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('pay')
                    ->label('Record payment')->icon('heroicon-m-banknotes')->color('success')
                    ->visible(fn (Invoice $r) => $r->balance > 0 && auth()->user()->can('process_payment'))
                    ->form([
                        Forms\Components\Placeholder::make('balance')->content(fn (Invoice $r) => '₱'.number_format((float) $r->balance, 2)),
                        Forms\Components\Select::make('payment_type')->required()->default('cash')
                            ->options(['cash' => 'Cash', 'credit_card' => 'Credit card', 'eftpos' => 'EFTPOS', 'cheque' => 'Cheque'])->live(),
                        Forms\Components\TextInput::make('amount')->numeric()->prefix('₱')->required()
                            ->default(fn (Invoice $r) => $r->balance),
                        Forms\Components\TextInput::make('cash_received')->numeric()->prefix('₱')
                            ->visible(fn (Forms\Get $get) => $get('payment_type') === 'cash'),
                        Forms\Components\TextInput::make('reference'),
                    ])
                    ->action(function (Invoice $r, array $data) {
                        $payment = Payment::create([
                            'client_id' => $r->client_id,
                            'location_id' => $r->location_id,
                            'payment_type' => $data['payment_type'],
                            'amount' => $data['amount'],
                            'cash_received' => $data['cash_received'] ?? null,
                            'reference' => $data['reference'] ?? null,
                            'received_at' => now(),
                        ]);
                        $payment->allocateTo([$r]);
                        Notification::make()->title('Payment recorded')->success()->send();
                    }),
                Tables\Actions\Action::make('void')
                    ->icon('heroicon-m-x-circle')->color('gray')->requiresConfirmation()
                    ->visible(fn (Invoice $r) => $r->status !== 'void' && $r->amount_paid == 0 && auth()->user()->can('update_invoice'))
                    ->action(fn (Invoice $r) => $r->update(['status' => 'void'])),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('invoice_no'),
                Infolists\Components\TextEntry::make('invoice_date')->date('d M Y'),
                Infolists\Components\TextEntry::make('client.full_name')->label('Client'),
                Infolists\Components\TextEntry::make('status')->badge(),
            ]),
            Infolists\Components\RepeatableEntry::make('items')->schema([
                Infolists\Components\TextEntry::make('description')->columnSpan(2),
                Infolists\Components\TextEntry::make('qty')->numeric(),
                Infolists\Components\TextEntry::make('unit_price_ex_tax')->money('PHP')->label('Unit (ex tax)'),
                Infolists\Components\TextEntry::make('discount_pct')->suffix('%')->label('Disc'),
                Infolists\Components\TextEntry::make('line_total_inc_tax')->money('PHP')->label('Total'),
            ])->columns(6),
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('subtotal_ex_tax')->money('PHP')->label('Subtotal (ex tax)'),
                Infolists\Components\TextEntry::make('tax_total')->money('PHP')->label('VAT'),
                Infolists\Components\TextEntry::make('total')->money('PHP')->weight('bold'),
                Infolists\Components\TextEntry::make('balance')->money('PHP'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
