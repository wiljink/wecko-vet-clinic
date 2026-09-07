<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\Payment;
use App\Support\ClientLedger;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Financials 7.1 — Account Balances: a client's statement of account with aged
 * debt, plus advance-payment and adjustment actions.
 */
class AccountBalances extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Account Balances';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.account-balances';

    protected static ?string $title = 'Account Balances';

    public ?int $clientId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_any_invoice') ?? false;
    }

    public function mount(): void
    {
        $this->clientId = request()->integer('client') ?: null;
        $this->form->fill(['clientId' => $this->clientId]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('clientId')->label('Client')
                ->options(fn () => Client::orderBy('surname')->get()->mapWithKeys(fn (Client $c) => [$c->id => $c->full_name]))
                ->searchable()->live()
                ->afterStateUpdated(fn ($state) => $this->clientId = $state),
        ])->statePath('data');
    }

    public function getClientProperty(): ?Client
    {
        return $this->clientId ? Client::find($this->clientId) : null;
    }

    public function getLedgerProperty(): ?array
    {
        if (! $client = $this->getClientProperty()) {
            return null;
        }

        $from = CarbonImmutable::now()->subMonths(6)->startOfMonth();

        return [
            'statement' => ClientLedger::statement($client, $from->toMutable(), now()),
            'aging' => ClientLedger::aging($client),
            'balance' => $client->currentBalance(),
        ];
    }

}
