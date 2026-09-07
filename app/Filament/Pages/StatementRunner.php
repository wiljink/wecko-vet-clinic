<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\StatementRun;
use App\Services\ReminderDispatcher;
use App\Support\ClientLedger;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Financials 7.2 — batch account statements: pick a period, a minimum balance,
 * an optional bookkeeping / late fee, and send by email or queue for print.
 */
class StatementRunner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Statement Run';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.statement-runner';

    protected static ?string $title = 'Account Statement Run';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('run_statements') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->subMonth()->startOfMonth(),
            'to_date' => now(),
            'min_balance' => 0,
            'fee_type' => 'none',
            'exclude_no_activity' => true,
            'channel' => 'email',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('from_date')->required(),
            DatePicker::make('to_date')->required(),
            TextInput::make('min_balance')->numeric()->prefix('₱')->default(0)
                ->helperText('Skip accounts under this balance.'),
            Select::make('fee_type')->label('Bookkeeping / late fee')->options([
                'none' => 'None', 'fixed' => 'Fixed amount', 'percent' => 'Percent of balance',
            ])->default('none')->live(),
            TextInput::make('fee_value')->numeric()->visible(fn ($get) => $get('fee_type') !== 'none'),
            Toggle::make('exclude_no_activity')->label('Exclude accounts with no transactions in the period')->default(true),
            Select::make('channel')->options(['email' => 'Email', 'print' => 'Print queue'])->default('email'),
        ])->columns(2)->statePath('data');
    }

    public function run(): void
    {
        abort_unless(Auth::user()->can('run_statements'), 403);

        $state = $this->form->getState();
        $from = CarbonImmutable::parse($state['from_date']);
        $to = CarbonImmutable::parse($state['to_date']);

        $run = StatementRun::create($state);
        $count = 0;

        Client::where('is_active', true)->with('addresses')->chunk(100, function ($clients) use ($run, $from, $to, $state, &$count) {
            foreach ($clients as $client) {
                $balance = $client->currentBalance();

                if ($balance < (float) $state['min_balance']) {
                    continue;
                }

                $statement = ClientLedger::statement($client, $from->toMutable(), $to->toMutable());

                if ($state['exclude_no_activity'] && $statement['rows']->isEmpty()) {
                    continue;
                }

                $fee = $run->feeFor($balance);
                if ($fee > 0) {
                    $client->accountAdjustments()->create([
                        'direction' => 'debit',
                        'reason' => 'Bookkeeping / late-payment fee',
                        'amount' => $fee,
                        'adjusted_on' => $to->toDateString(),
                    ]);
                }

                if ($state['channel'] === 'email' && $client->email && $client->reminders_by_email) {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Dear {$client->full_name},\n\nYour account statement to {$to->format('d M Y')} shows a balance of ₱".number_format($balance + $fee, 2).
                        ".\nA detailed statement is attached in your client portal.\n\nThank you.",
                        fn ($m) => $m->to($client->email)->subject('Your account statement')
                    );
                }

                $count++;
            }
        });

        $run->update(['statement_count' => $count]);

        Notification::make()->title("Statement run complete — {$count} statement(s) issued by {$state['channel']}")->success()->send();
    }
}
