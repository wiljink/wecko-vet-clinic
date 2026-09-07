<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PatientResource\Pages;
use App\Filament\Resources\PatientResource\RelationManagers;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Patient;
use App\Models\PatientTransfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PatientResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Clients & Patients';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Owner & Identity')->columns(3)->schema([
                Forms\Components\Select::make('client_id')->label('Owner (client)')->required()
                    ->relationship('client', 'surname')
                    ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->full_name)
                    ->searchable(['surname', 'given_name', 'company_name'])->preload()
                    ->default(fn () => request()->integer('client_id') ?: null),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('microchip_no')->label('Microchip #'),
                Forms\Components\Select::make('species_id')->relationship('species', 'name')
                    ->searchable()->preload()->live()->afterStateUpdated(fn (Forms\Set $set) => $set('breed_id', null)),
                Forms\Components\Select::make('breed_id')
                    ->options(fn (Forms\Get $get) => $get('species_id')
                        ? Breed::where('species_id', $get('species_id'))->orderBy('name')->pluck('name', 'id')
                        : [])
                    ->searchable(),
                Forms\Components\Select::make('colour_id')->relationship('colour', 'name')->searchable()->preload(),
                Forms\Components\Select::make('gender')->options([
                    'female' => 'Female', 'male' => 'Male', 'unknown' => 'Unknown',
                ])->default('unknown')->required(),
            ]),

            Forms\Components\Section::make('Age & Desexing')->columns(4)->schema([
                Forms\Components\DatePicker::make('birth_date')->label('Birth date (if known)')
                    ->helperText('Exact age is calculated from this and drives de-sexing reminders.'),
                Forms\Components\TextInput::make('age_years')->numeric()->label('Age — years'),
                Forms\Components\TextInput::make('age_months')->numeric()->label('months'),
                Forms\Components\TextInput::make('age_weeks')->numeric()->label('weeks'),
                Forms\Components\Select::make('neuter_status')->options([
                    'not_neutered' => 'Not neutered',
                    'neutered' => 'Neutered / de-sexed',
                    'owner_declines' => 'Owner declines (suppress reminder)',
                ])->default('not_neutered')->live(),
                Forms\Components\DatePicker::make('date_neutered')
                    ->visible(fn (Forms\Get $get) => $get('neuter_status') === 'neutered'),
            ]),

            Forms\Components\Section::make('Care & Warnings')->columns(3)->schema([
                Forms\Components\TextInput::make('weight')->numeric()->suffix('kg'),
                Forms\Components\TextInput::make('temperament'),
                Forms\Components\TextInput::make('insurance_policy_no')->label('Insurance policy #'),
                Forms\Components\TextInput::make('heart_wormer'),
                Forms\Components\TextInput::make('int_wormer')->label('Intestinal wormer'),
                Forms\Components\TextInput::make('flea_control'),
                Forms\Components\TextInput::make('diet'),
                Forms\Components\Textarea::make('behavioural_warning')->columnSpan(2)
                    ->helperText('Shown prominently on the patient record.'),
            ]),

            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\FileUpload::make('photo_path')->image()->directory('patient-photos')->avatar(),
                Forms\Components\DatePicker::make('first_visit_on'),
                Forms\Components\DatePicker::make('last_visit_on'),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')->label('')->circular()->height(34),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Owner')
                    ->searchable(['surname', 'given_name'])->sortable(),
                Tables\Columns\TextColumn::make('species.name')->label('Species')->sortable(),
                Tables\Columns\TextColumn::make('breed.name')->label('Breed')->toggleable(),
                Tables\Columns\TextColumn::make('age_label')->label('Age'),
                Tables\Columns\TextColumn::make('gender')->badge(),
                Tables\Columns\TextColumn::make('microchip_no')->label('Microchip')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_visit_on')->date('d M Y')->label('Last visit')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
                Tables\Filters\SelectFilter::make('species_id')->relationship('species', 'name')->label('Species'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('transfer')
                    ->label('Transfer to new owner')->icon('heroicon-m-arrows-right-left')->color('warning')
                    ->form([
                        Forms\Components\Select::make('to_client_id')->label('New owner')->required()
                            ->options(fn (Patient $record) => Client::query()
                                ->whereNot('id', $record->client_id)->active()
                                ->get()->pluck('full_name', 'id'))
                            ->searchable(),
                        Forms\Components\Textarea::make('reason'),
                        Forms\Components\Placeholder::make('note')->content(
                            'All consultations and medical history move to the new owner. '
                            .'Financial records stay with the current owner — settle the account first.'
                        ),
                    ])
                    ->action(function (Patient $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            PatientTransfer::create([
                                'patient_id' => $record->id,
                                'from_client_id' => $record->client_id,
                                'to_client_id' => $data['to_client_id'],
                                'transferred_by' => auth()->id(),
                                'reason' => $data['reason'] ?? null,
                                'transferred_at' => now(),
                            ]);

                            // Move clinical records; leave invoices/payments with the old owner.
                            $record->consultations()->update(['client_id' => $data['to_client_id']]);
                            $record->reminders()->update(['client_id' => $data['to_client_id']]);
                            $record->appointments()
                                ->where('starts_at', '>=', now())
                                ->update(['client_id' => $data['to_client_id']]);

                            $record->update(['client_id' => $data['to_client_id']]);
                        });

                        Notification::make()->title('Patient transferred')->success()->send();
                    }),
                Tables\Actions\Action::make('history')
                    ->label('History PDF')->icon('heroicon-m-document-arrow-down')->color('gray')
                    ->url(fn (Patient $record) => route('patients.history', $record))
                    ->openUrlInNewTab()
                    ->visible(fn () => \Illuminate\Support\Facades\Route::has('patients.history')),
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
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }
}
