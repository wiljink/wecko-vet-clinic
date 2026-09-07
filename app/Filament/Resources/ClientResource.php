<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Clients & Patients';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'surname';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('Personal')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('title_id')->relationship('title', 'name')->preload(),
                        Forms\Components\TextInput::make('given_name'),
                        Forms\Components\TextInput::make('middle_name'),
                        Forms\Components\TextInput::make('surname')->required()
                            ->helperText('The only mandatory field.'),
                        Forms\Components\TextInput::make('company_name'),
                        Forms\Components\Toggle::make('use_company_as_first_address')
                            ->label('Use company name as first address line'),
                        Forms\Components\TextInput::make('partner_name')
                            ->helperText('Trusted family member who may bring patients in under this account.'),
                        Forms\Components\TextInput::make('email')->email(),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('Phones')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('residence_phone')->tel(),
                        Forms\Components\TextInput::make('mobile_phone')->tel(),
                        Forms\Components\TextInput::make('office_phone')->tel(),
                        Forms\Components\TextInput::make('office_ext')->label('Office ext.'),
                        Forms\Components\TextInput::make('fax')->tel(),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('Account')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('account_name_reference_id')
                            ->label('Account reference')->relationship('accountReference', 'name')->preload()
                            ->helperText('Financial standing — e.g. ACCOUNT OK, BAD DEBTOR.'),
                        Forms\Components\Select::make('referral_id')->label('Referred by')
                            ->relationship('referral', 'name')->preload()->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                            ]),
                        Forms\Components\Select::make('payment_type')->options([
                            'Cash' => 'Cash', 'Account' => 'Account', 'Card' => 'Card', 'GCash' => 'GCash',
                        ]),
                        Forms\Components\Select::make('statement_type')->options([
                            'email' => 'Email', 'print' => 'Print', 'none' => 'No statement',
                        ])->default('email'),
                        Forms\Components\TextInput::make('discount_pct')->label('Standing discount %')
                            ->numeric()->default(0)->suffix('%'),
                        Forms\Components\DatePicker::make('account_opened_on'),
                    ]),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')->default(true)
                        ->helperText('Inactive clients are hidden by default. A client with references on file cannot be deleted — make them inactive instead.'),
                ]),

                Forms\Components\Tabs\Tab::make('Communication')->schema([
                    Forms\Components\Placeholder::make('note')
                        ->content('SMS needs a mobile number on file; email needs an email address.'),
                    Forms\Components\Fieldset::make('Appointments')->schema([
                        Forms\Components\Checkbox::make('appointments_by_letter')->label('Letter'),
                        Forms\Components\Checkbox::make('appointments_by_email')->label('Email'),
                        Forms\Components\Checkbox::make('appointments_by_sms')->label('SMS'),
                    ])->columns(3),
                    Forms\Components\Fieldset::make('Reminders')->schema([
                        Forms\Components\Checkbox::make('reminders_by_letter')->label('Letter'),
                        Forms\Components\Checkbox::make('reminders_by_email')->label('Email'),
                        Forms\Components\Checkbox::make('reminders_by_sms')->label('SMS'),
                    ])->columns(3),
                    Forms\Components\Fieldset::make('Marketing')->schema([
                        Forms\Components\Checkbox::make('marketing_by_letter')->label('Letter'),
                        Forms\Components\Checkbox::make('marketing_by_email')->label('Email'),
                        Forms\Components\Checkbox::make('marketing_by_sms')->label('SMS'),
                    ])->columns(3),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('surname')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('given_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title.name')->label('Title')->toggleable(),
                Tables\Columns\TextColumn::make('company_name')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mobile_phone')->searchable()->label('Mobile'),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('accountReference.name')->label('Account')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('patients_count')->counts('patients')->label('Patients')->badge(),
                Tables\Columns\TextColumn::make('balance')->label('Balance')
                    ->state(fn (Client $r) => $r->currentBalance())->money('PHP')->alignEnd(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->defaultSort('surname')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
                Tables\Filters\SelectFilter::make('account_name_reference_id')
                    ->label('Account reference')->relationship('accountReference', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('makeInactive')
                    ->label(fn (Client $r) => $r->is_active ? 'Make inactive' : 'Reactivate')
                    ->icon('heroicon-m-no-symbol')->color('gray')->requiresConfirmation()
                    ->action(fn (Client $r) => $r->update(['is_active' => ! $r->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\PatientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
