<?php

namespace App\Filament\Pages;

use App\Models\CompanySetting;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Setup > Company Settings — the single-row {@see CompanySetting} record:
 * company information, country/tax, operational flags and the default consult
 * medical descriptions.
 */
class CompanySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Company Settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.company-settings';

    protected static ?string $title = 'Company Settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_any_company_setting') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(CompanySetting::current()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Company Information')
                    ->description('Printed on invoices, statements and certificates.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('company_name')->required()
                                ->helperText('Printed as the clinic name on invoices, statements and certificates.'),
                            TextInput::make('tin')->label('TIN')
                                ->helperText('BIR Tax Identification Number printed on official receipts.'),
                            TextInput::make('email')->email()
                                ->helperText('Contact email shown on printed documents and client statements.'),
                            TextInput::make('phone')
                                ->helperText('Contact number shown on printed documents and client statements.'),
                            TextInput::make('fax')
                                ->helperText('Fax number shown on printed documents, if the clinic still uses one.'),
                            TextInput::make('website')->url()->prefix('https://')
                                ->helperText('Shown on printed documents and client-facing statements.'),
                        ]),
                        Textarea::make('address')->rows(3)
                            ->helperText('Clinic address printed on invoices, statements and certificates.'),
                    ]),

                Section::make('Country & Tax')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('country')
                            ->helperText('Used to format addresses and pick country-specific defaults.'),
                        TextInput::make('currency_symbol')->required()->maxLength(4)
                            ->helperText('Prefixes every peso amount shown or printed by the system.'),
                        TextInput::make('date_format')->helperText('PHP date() format, e.g. d/m/Y'),
                        TextInput::make('tax_rate')->numeric()->required()->suffix('%')
                            ->helperText('Rate applied to taxable sales when calculating invoice totals.'),
                        TextInput::make('tax_label')->required()->helperText('e.g. VAT, GST'),
                        TextInput::make('accounting_period_days')->numeric()
                            ->helperText('Aging bucket width — 15 or 30 days'),
                    ]),
                    KeyValue::make('coin_denominations')
                        ->label('Cash denominations (Balance the Till)')
                        ->keyLabel('#')->valueLabel('Denomination')->addable()->reorderable()
                        ->helperText('Denominations counted on the till-balancing sheet at end of day.'),
                ]),

                Section::make('Point of Sale & Receipts')
                    ->description('Barcode symbology, the default tender and the text printed on sales receipts.')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('auto_generate_barcode')
                                ->helperText('Assign an in-store barcode to new stock items automatically.'),
                            TextInput::make('barcode_symbology')->default('C128')->disabled()
                                ->helperText('Code 128 (fixed).'),
                            \Filament\Forms\Components\Select::make('pos_default_payment_type')
                                ->label('Default tender')
                                ->options(['cash' => 'Cash', 'credit_card' => 'Credit card', 'eftpos' => 'EFTPOS', 'cheque' => 'Cheque'])
                                ->default('cash')
                                ->helperText('Pre-selected payment method on the counter sale screen; cashiers can change it per sale.'),
                            Toggle::make('pos_print_receipt')->label('Offer receipt after each sale')->default(true)
                                ->helperText('Prompt the cashier to print a receipt every time a counter sale is completed.'),
                        ]),
                        Textarea::make('receipt_header')->rows(2)->helperText('Printed under the clinic name, e.g. "VAT Reg. TIN 000-000-000".'),
                        Textarea::make('receipt_footer')->rows(2)->helperText('Printed at the bottom, e.g. "Thank you — no refunds without receipt".'),
                    ]),

                Section::make('Operational Defaults')->schema([
                    Grid::make(3)->schema([
                        Toggle::make('show_reminders_on_login')
                            ->helperText('Pop up due reminders on the dashboard as soon as a user logs in.'),
                        Toggle::make('auto_generate_product_code')
                            ->helperText('Assign a product code to new inventory items automatically instead of asking staff to key one in.'),
                        Toggle::make('display_patients_per_client')
                            ->helperText('Show each patient count on the client list.'),
                        Toggle::make('open_discounting')
                            ->helperText('Show a visible DISCOUNT line on invoices'),
                        TextInput::make('reminder_days_window')->numeric()->suffix('days')
                            ->helperText('How many days ahead reminders are pulled into the due list.'),
                        TextInput::make('default_desex_age_months')->numeric()->suffix('months')
                            ->helperText('Suggested age used when scheduling desexing reminders for new patients.'),
                        TextInput::make('default_dispense_fee')->numeric()->prefix('₱')
                            ->helperText('Default fee added when dispensing medication, editable per sale.'),
                        TextInput::make('default_injection_fee')->numeric()->prefix('₱')
                            ->helperText('Default fee added when administering an injection, editable per sale.'),
                    ]),
                ]),

                Section::make('Default Consult Medical Descriptions')
                    ->description('Pre-filled into every new consultation; the vet edits as needed.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('default_history')->rows(2)
                            ->helperText('Pre-fills the History field on every new consultation.'),
                        Textarea::make('default_examination')->rows(3)
                            ->helperText('Pre-fills the Examination field on every new consultation.'),
                        Textarea::make('default_tests')->rows(2)
                            ->helperText('Pre-fills the Tests field on every new consultation.'),
                        Textarea::make('default_differential_diagnosis')->rows(2)
                            ->helperText('Pre-fills the Differential Diagnosis field on every new consultation.'),
                        Textarea::make('default_consult_diagnosis')->rows(2)
                            ->helperText('Pre-fills the Diagnosis field on every new consultation.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->can('update_company_setting'), 403);

        CompanySetting::current()->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
