<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Client;
use App\Models\Patient;
use App\Support\LocationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Calendar';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('client_id')->label('Client')
                    ->relationship('client', 'surname')
                    ->getOptionLabelFromRecordUsing(fn (Client $c) => $c->full_name)
                    ->searchable(['surname', 'given_name'])->preload()->live()
                    ->helperText('Who the appointment is booked for.'),
                Forms\Components\Select::make('patient_id')->label('Patient')
                    ->options(fn (Forms\Get $get) => $get('client_id')
                        ? Patient::where('client_id', $get('client_id'))->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->helperText('Which of the client\'s patients this visit is for.'),
                Forms\Components\Select::make('provider_id')->label('Provider')
                    ->relationship('provider', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_provider', true))
                    ->searchable()->preload()
                    ->helperText('Vet or staff member who will see the patient.'),
                LocationContext::selectField()->helperText('Branch where the appointment takes place.'),
                Forms\Components\Select::make('room_id')->label('Room')
                    ->relationship('room', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->rooms())
                    ->searchable()->preload()
                    ->helperText('Exam room or bay, if this branch tracks them.'),
                Forms\Components\DateTimePicker::make('starts_at')->required()->seconds(false)->native(false)->live()
                    ->helperText('Date and time the appointment is scheduled to begin.'),
                Forms\Components\Select::make('duration_minutes')->options([
                    10 => '10 min', 15 => '15 min', 20 => '20 min', 30 => '30 min',
                    45 => '45 min', 60 => '1 hour', 90 => '1½ hours', 120 => '2 hours',
                ])->default(15)->required()
                    ->helperText('How long the slot is blocked out on the calendar.'),
                Forms\Components\Toggle::make('all_day')->label('All-day event')
                    ->helperText('Blocks out the whole day instead of a specific time slot.'),
                Forms\Components\Select::make('appointment_status_id')->label('Status')
                    ->relationship('status', 'name')
                    ->default(fn () => AppointmentStatus::where('is_default', true)->value('id'))->preload()
                    ->helperText('Where the appointment stands, e.g. confirmed, arrived, completed.'),
                Forms\Components\Select::make('appointment_reason_id')->label('Reason')
                    ->relationship('reason', 'reason')->searchable()->preload()
                    ->helperText('Carried forward to the consultation.'),
                Forms\Components\Select::make('appointment_label_id')->label('Label')
                    ->relationship('label', 'name')->preload()
                    ->helperText('Colour-coded tag for scanning the calendar at a glance, e.g. Surgery, Checkup.'),
                Forms\Components\Textarea::make('notes')->columnSpanFull()
                    ->helperText('Internal notes about this appointment; not shown to the client.'),
            ]),

            Forms\Components\Section::make('Recurrence')->collapsed()->columns(3)->schema([
                Forms\Components\Select::make('recurrence_freq')->label('Repeats')
                    ->options([
                        'daily' => 'Daily', 'weekday' => 'Every weekday', 'weekly' => 'Weekly',
                        'monthly' => 'Monthly', 'yearly' => 'Yearly',
                    ])->live()
                    ->helperText('Leave blank for a one-off appointment; set this to generate a repeating series.'),
                Forms\Components\TextInput::make('recurrence_interval')->numeric()->default(1)
                    ->label('Every N')->visible(fn (Forms\Get $get) => filled($get('recurrence_freq')))
                    ->helperText('E.g. 2 with "Weekly" repeats the appointment every other week.'),
                Forms\Components\DatePicker::make('recurrence_until')->label('Until')
                    ->visible(fn (Forms\Get $get) => filled($get('recurrence_freq')))
                    ->helperText('Last date on which a recurring occurrence can be generated.'),
                Forms\Components\TextInput::make('recurrence_count')->numeric()->label('Or after N occurrences')->maxValue(60)
                    ->visible(fn (Forms\Get $get) => filled($get('recurrence_freq')))
                    ->helperText('Stop generating new occurrences after this many appointments, instead of using an end date.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereNull('recurrence_parent_id')->orWhereColumn('id', 'recurrence_parent_id'))
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')->dateTime('D d M, H:i')->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(['surname'])->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient'),
                Tables\Columns\TextColumn::make('provider.name')->label('Provider')->sortable(),
                Tables\Columns\TextColumn::make('location.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('room.name')->label('Room')->toggleable(),
                Tables\Columns\TextColumn::make('reason.reason')->label('Reason')->toggleable(),
                Tables\Columns\TextColumn::make('status.name')->label('Status')->badge()
                    ->color(fn (Appointment $r) => \Filament\Support\Colors\Color::hex($r->status?->color ?? '#64748b')),
                Tables\Columns\IconColumn::make('recurrence_freq')->label('Recurs')->boolean()
                    ->trueIcon('heroicon-m-arrow-path')->falseIcon('')->state(fn (Appointment $r) => (bool) $r->recurrence_freq),
                Tables\Columns\IconColumn::make('reminder_sent_at')->label('Reminded')->boolean()->toggleable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('provider_id')->relationship('provider', 'name')->label('Provider'),
                Tables\Filters\SelectFilter::make('appointment_status_id')->relationship('status', 'name')->label('Status'),
                Tables\Filters\Filter::make('upcoming')->label('Upcoming only')->default()
                    ->query(fn ($query) => $query->where('starts_at', '>=', now()->startOfDay())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('startConsult')
                    ->label('Start consult')->icon('heroicon-m-clipboard-document-check')->color('success')
                    ->visible(fn () => \Illuminate\Support\Facades\Route::has('filament.admin.resources.consultations.create')
                        && auth()->user()->can('create_consultation'))
                    ->url(fn (Appointment $r) => ConsultationResource::getUrl('create', ['appointment' => $r->id])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sendReminder')
                        ->label('Send appointment reminder')->icon('heroicon-m-paper-airplane')
                        ->form([
                            Forms\Components\Select::make('channel')->options([
                                'email' => 'Email', 'sms' => 'SMS',
                            ])->default('email')->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $sent = app(\App\Services\ReminderDispatcher::class)
                                ->sendAppointmentReminders($records, $data['channel']);
                            Notification::make()->title("{$sent} reminder(s) sent")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
