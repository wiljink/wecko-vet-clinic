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
                            TextInput::make('company_name')->required(),
                            TextInput::make('tin')->label('TIN'),
                            TextInput::make('email')->email(),
                            TextInput::make('phone'),
                            TextInput::make('fax'),
                            TextInput::make('website')->url()->prefix('https://'),
                        ]),
                        Textarea::make('address')->rows(3),
                    ]),

                Section::make('Country & Tax')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('country'),
                        TextInput::make('currency_symbol')->required()->maxLength(4),
                        TextInput::make('date_format')->helperText('PHP date() format, e.g. d/m/Y'),
                        TextInput::make('tax_rate')->numeric()->required()->suffix('%'),
                        TextInput::make('tax_label')->required()->helperText('e.g. VAT, GST'),
                        TextInput::make('accounting_period_days')->numeric()
                            ->helperText('Aging bucket width — 15 or 30 days'),
                    ]),
                    KeyValue::make('coin_denominations')
                        ->label('Cash denominations (Balance the Till)')
                        ->keyLabel('#')->valueLabel('Denomination')->addable()->reorderable(),
                ]),

                Section::make('Operational Defaults')->schema([
                    Grid::make(3)->schema([
                        Toggle::make('show_reminders_on_login'),
                        Toggle::make('auto_generate_product_code'),
                        Toggle::make('display_patients_per_client'),
                        Toggle::make('open_discounting')
                            ->helperText('Show a visible DISCOUNT line on invoices'),
                        TextInput::make('reminder_days_window')->numeric()->suffix('days'),
                        TextInput::make('default_desex_age_months')->numeric()->suffix('months'),
                        TextInput::make('default_dispense_fee')->numeric()->prefix('₱'),
                        TextInput::make('default_injection_fee')->numeric()->prefix('₱'),
                    ]),
                ]),

                Section::make('Default Consult Medical Descriptions')
                    ->description('Pre-filled into every new consultation; the vet edits as needed.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('default_history')->rows(2),
                        Textarea::make('default_examination')->rows(3),
                        Textarea::make('default_tests')->rows(2),
                        Textarea::make('default_differential_diagnosis')->rows(2),
                        Textarea::make('default_consult_diagnosis')->rows(2),
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
