<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\BankingBatchResource\Pages;
use App\Models\BankingBatch;
use App\Models\Payment;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BankingBatchResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = BankingBatch::class;

    protected static string $permissionKey = 'banking_batch';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Banking';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable(),
                Tables\Columns\TextColumn::make('banking_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Branch')->placeholder('All branches')->toggleable(),
                Tables\Columns\TextColumn::make('cash_total')->money('PHP'),
                Tables\Columns\TextColumn::make('cheque_total')->money('PHP'),
                Tables\Columns\TextColumn::make('eftpos_total')->money('PHP')->label('EFTPOS'),
                Tables\Columns\TextColumn::make('card_total')->money('PHP')->label('Card'),
                Tables\Columns\TextColumn::make('grand_total')->money('PHP')->weight('bold'),
                Tables\Columns\TextColumn::make('payments_count')->counts('payments')->label('Payments')->badge(),
            ])
            ->defaultSort('banking_date', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('bankNow')
                    ->label('Bank un-banked payments')->icon('heroicon-m-arrow-down-on-square')
                    ->form([
                        Forms\Components\DatePicker::make('up_to')->label('Up to and including')->default(now())->required(),
                    ])
                    ->action(function (array $data) {
                        $pending = Payment::where('banked', false)->where('is_refund', false)
                            ->whereDate('received_at', '<=', $data['up_to'])->count();

                        if ($pending === 0) {
                            Notification::make()->title('No un-banked payments to bank')->warning()->send();

                            return;
                        }

                        $batch = BankingBatch::bankUpTo(\Illuminate\Support\Carbon::parse($data['up_to']));
                        Notification::make()->title("Banked {$pending} payment(s) — {$batch->reference}, ₱".number_format((float) $batch->grand_total, 2))->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->icon('heroicon-m-eye')
                    ->modalContent(fn (BankingBatch $r) => view('filament.modals.banking-batch', ['batch' => $r->load('payments.client')]))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankingBatches::route('/'),
        ];
    }
}
