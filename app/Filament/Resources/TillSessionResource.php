<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\TillSessionResource\Pages;
use App\Models\CompanySetting;
use App\Models\TillSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TillSessionResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = TillSession::class;

    protected static string $permissionKey = 'till_session';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Balance the Till';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        $denoms = CompanySetting::current()->coin_denominations ?: [1000, 500, 200, 100, 50, 20, 10, 5, 1];

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\DatePicker::make('session_date')->default(now())->required(),
                Forms\Components\Select::make('location_id')->relationship('location', 'name')->searchable(),
                Forms\Components\TextInput::make('opening_float')->numeric()->prefix('₱')->default(0)->required(),
            ]),
            Forms\Components\Fieldset::make('Count the drawer')->schema(
                collect($denoms)->map(fn ($d) => Forms\Components\TextInput::make("denominations.{$d}")
                    ->label('₱'.number_format((int) $d))->numeric()->default(0)->minValue(0))->all()
            )->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('location.name')->toggleable(),
                Tables\Columns\TextColumn::make('opening_float')->money('PHP'),
                Tables\Columns\TextColumn::make('expected_cash')->money('PHP'),
                Tables\Columns\TextColumn::make('counted_cash')->money('PHP'),
                Tables\Columns\TextColumn::make('variance')->money('PHP')
                    ->color(fn ($state) => abs((float) $state) < 0.01 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('status')->badge()->colors(['warning' => 'open', 'success' => 'closed']),
            ])
            ->defaultSort('session_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (TillSession $r) => $r->status === 'open'),
                Tables\Actions\Action::make('close')
                    ->label('Close & reconcile')->icon('heroicon-m-lock-closed')->color('success')
                    ->visible(fn (TillSession $r) => $r->status === 'open')
                    ->action(function (TillSession $r) {
                        $r->close();
                        $v = (float) $r->variance;
                        Notification::make()
                            ->title('Till closed — expected ₱'.number_format((float) $r->expected_cash, 2).
                                ', counted ₱'.number_format((float) $r->counted_cash, 2))
                            ->body(abs($v) < 0.01 ? 'Balanced.' : ($v > 0 ? "Over by ₱".number_format($v, 2) : "Short by ₱".number_format(abs($v), 2)))
                            ->{abs($v) < 0.01 ? 'success' : 'warning'}()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTillSessions::route('/'),
            'create' => Pages\CreateTillSession::route('/create'),
            'edit' => Pages\EditTillSession::route('/{record}/edit'),
        ];
    }
}
