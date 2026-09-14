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
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
                    ->default(fn () => request()->integer('client_id') ?: null)
                    ->helperText('Determines who is billed and who receives reminders for this patient.'),
                Forms\Components\TextInput::make('name')->required()
                    ->helperText('Patient\'s name as the owner knows it.'),
                Forms\Components\TextInput::make('microchip_no')->label('Microchip #')
                    ->helperText('Used to confirm identity if the patient is lost or transferred between owners.'),
                Forms\Components\Select::make('species_id')->relationship('species', 'name')
                    ->searchable()->preload()->live()->afterStateUpdated(fn (Forms\Set $set) => $set('breed_id', null))
                    ->helperText('Determines which breed list and reference ranges apply.'),
                Forms\Components\Select::make('breed_id')
                    ->options(fn (Forms\Get $get) => $get('species_id')
                        ? Breed::where('species_id', $get('species_id'))->orderBy('name')->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->helperText('Narrowed to the selected species; some breeds carry specific health alerts.'),
                Forms\Components\Select::make('colour_id')->relationship('colour', 'name')->searchable()->preload()
                    ->helperText('Coat/colour — helps identify the patient at a glance and on lost-pet notices.'),
                Forms\Components\Select::make('gender')->options([
                    'female' => 'Female', 'male' => 'Male', 'unknown' => 'Unknown',
                ])->default('unknown')->required()
                    ->helperText('Biological sex — separate from the de-sexing status recorded below.'),
            ]),

            Forms\Components\Section::make('Age & Desexing')->columns(4)->schema([
                Forms\Components\DatePicker::make('birth_date')->label('Birth date (if known)')
                    ->helperText('Exact age is calculated from this and drives de-sexing reminders.'),
                Forms\Components\TextInput::make('age_years')->numeric()->label('Age — years')
                    ->helperText('Estimated age, used only when the exact birth date is unknown.'),
                Forms\Components\TextInput::make('age_months')->numeric()->label('months')
                    ->helperText('Extra months on top of the years — useful for young animals.'),
                Forms\Components\TextInput::make('age_weeks')->numeric()->label('weeks')
                    ->helperText('Extra weeks — mainly for neonates and pocket pets.'),
                Forms\Components\Select::make('neuter_status')->options([
                    'not_neutered' => 'Not neutered',
                    'neutered' => 'Neutered / de-sexed',
                    'owner_declines' => 'Owner declines (suppress reminder)',
                ])->default('not_neutered')->live()
                    ->helperText('"Owner declines" suppresses the de-sexing reminder without marking the patient as neutered.'),
                Forms\Components\DatePicker::make('date_neutered')
                    ->visible(fn (Forms\Get $get) => $get('neuter_status') === 'neutered')
                    ->helperText('Date of the de-sexing procedure.'),
            ]),

            Forms\Components\Section::make('Care & Warnings')->columns(3)->schema([
                Forms\Components\TextInput::make('weight')->numeric()->suffix('kg')
                    ->helperText('Latest recorded weight — used to calculate medication dosages.'),
                Forms\Components\TextInput::make('temperament')
                    ->helperText('Notable handling risk, e.g. nervous, bites, needs a muzzle.'),
                Forms\Components\TextInput::make('insurance_policy_no')->label('Insurance policy #')
                    ->helperText('Reference number used when submitting pet insurance claims.'),
                Forms\Components\TextInput::make('heart_wormer')
                    ->helperText('Current heartworm prevention product and schedule.'),
                Forms\Components\TextInput::make('int_wormer')->label('Intestinal wormer')
                    ->helperText('Current intestinal worming product and schedule.'),
                Forms\Components\TextInput::make('flea_control')
                    ->helperText('Current flea/tick prevention product.'),
                Forms\Components\TextInput::make('diet')
                    ->helperText('Special diet or feeding instructions relevant to consultations.'),
                Forms\Components\Textarea::make('behavioural_warning')->columnSpan(2)
                    ->helperText('Shown prominently on the patient record.'),
            ]),

            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\FileUpload::make('photo_path')->image()->directory('patient-photos')->avatar()
                    ->helperText('Shown on the patient record and printed history — helps staff identify the animal quickly.'),
                Forms\Components\DatePicker::make('first_visit_on')
                    ->helperText('Date of this patient\'s first visit to the clinic.'),
                Forms\Components\DatePicker::make('last_visit_on')
                    ->helperText('Updated as consultations are recorded; used to flag lapsed patients.'),
                Forms\Components\Textarea::make('notes')->columnSpanFull()
                    ->helperText('General staff notes — not shown to the client.'),
                Forms\Components\Toggle::make('is_active')->default(true)
                    ->helperText('Inactive patients (e.g. deceased or transferred away) are hidden from default lists but keep their history.'),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Patient')->columns(4)->schema([
                Infolists\Components\ImageEntry::make('photo_path')->label('')->circular()->height(72),
                Infolists\Components\TextEntry::make('name')->size('lg')->weight('bold'),
                Infolists\Components\TextEntry::make('client.full_name')->label('Owner')
                    ->url(fn (Patient $record) => $record->client_id
                        ? ClientResource::getUrl('view', ['record' => $record->client_id])
                        : null),
                Infolists\Components\TextEntry::make('age_label')->label('Age'),
                Infolists\Components\TextEntry::make('species.name')->label('Species')->placeholder('—'),
                Infolists\Components\TextEntry::make('breed.name')->label('Breed')->placeholder('—'),
                Infolists\Components\TextEntry::make('colour.name')->label('Colour')->placeholder('—'),
                Infolists\Components\TextEntry::make('gender')->badge(),
                Infolists\Components\TextEntry::make('microchip_no')->label('Microchip #')->placeholder('—'),
                Infolists\Components\TextEntry::make('neuter_status')->label('De-sexing')->badge(),
                Infolists\Components\TextEntry::make('first_visit_on')->date('d M Y')->label('First visit')->placeholder('—'),
                Infolists\Components\TextEntry::make('last_visit_on')->date('d M Y')->label('Last visit')->placeholder('—'),
            ]),

            Infolists\Components\Section::make('Behavioural warning')
                ->visible(fn (Patient $record) => filled($record->behavioural_warning))
                ->schema([
                    Infolists\Components\TextEntry::make('behavioural_warning')->label('')->color('danger')->weight('bold'),
                ]),

            Infolists\Components\Section::make('History at a glance')->columns(4)->schema([
                Infolists\Components\TextEntry::make('consultations_count')->label('Consultations')
                    ->state(fn (Patient $record) => $record->consultations()->count())->badge()->color('primary'),
                Infolists\Components\TextEntry::make('vaccinations_count')->label('Vaccinations')
                    ->state(fn (Patient $record) => $record->vaccinations()->count())->badge()->color('success'),
                Infolists\Components\TextEntry::make('prescriptions_count')->label('Prescriptions')
                    ->state(fn (Patient $record) => $record->prescriptions()->count())->badge()->color('warning'),
                Infolists\Components\TextEntry::make('appointments_count')->label('Appointments')
                    ->state(fn (Patient $record) => $record->appointments()->count())->badge()->color('gray'),
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
                Tables\Actions\ViewAction::make(),
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
            RelationManagers\ConsultationsRelationManager::class,
            RelationManagers\VaccinationsRelationManager::class,
            RelationManagers\PrescriptionsRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'view' => Pages\ViewPatient::route('/{record}'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }
}
