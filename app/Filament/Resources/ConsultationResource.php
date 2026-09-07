<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ConsultationResource\Pages;
use App\Models\Appointment;
use App\Models\CompanySetting;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Product;
use App\Models\StandardConsult;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ConsultationResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Consultation::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Consultations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Select::make('patient_id')->label('Patient')->required()
                    ->options(fn () => Patient::with('client')->where('is_active', true)->get()
                        ->mapWithKeys(fn (Patient $p) => [$p->id => "{$p->name} — {$p->client?->full_name}"]))
                    ->searchable()->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($p = Patient::find($state)) {
                            $set('client_id', $p->client_id);
                            $set('weight', $p->weight);
                        }
                    })
                    ->default(fn () => Appointment::find(request()->integer('appointment'))?->patient_id),
                Forms\Components\Hidden::make('client_id')
                    ->default(fn () => Appointment::find(request()->integer('appointment'))?->client_id),
                Forms\Components\Select::make('provider_id')->label('Provider')
                    ->relationship('provider', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_provider', true))
                    ->searchable()->preload()
                    ->default(fn () => Appointment::find(request()->integer('appointment'))?->provider_id ?? Auth::id()),
                Forms\Components\Select::make('location_id')->relationship('location', 'name')->searchable()->preload()
                    ->default(fn () => Appointment::find(request()->integer('appointment'))?->location_id),
                Forms\Components\DateTimePicker::make('consult_date')->default(now())->seconds(false)->required(),
                Forms\Components\Select::make('appointment_reason_id')->label('Reason')
                    ->relationship('reason', 'reason')->searchable()->preload()
                    ->default(fn () => Appointment::find(request()->integer('appointment'))?->appointment_reason_id),
                Forms\Components\Hidden::make('appointment_id')
                    ->default(fn () => request()->integer('appointment') ?: null),
                Forms\Components\TextInput::make('weight')->numeric()->suffix('kg'),
                Forms\Components\TextInput::make('temperature')->numeric()->suffix('°C'),
            ]),

            Forms\Components\Section::make('Notes')->columns(2)->collapsible()->schema([
                Forms\Components\Textarea::make('history')->rows(3),
                Forms\Components\Textarea::make('examination')->rows(3),
                Forms\Components\Textarea::make('tests')->rows(2),
                Forms\Components\Textarea::make('comment')->rows(2),
                Forms\Components\Textarea::make('differential_diagnosis')->rows(2),
                Forms\Components\Textarea::make('consult_diagnosis')->rows(2),
                Forms\Components\Textarea::make('treatment')->rows(2)->columnSpanFull(),
                Forms\Components\Textarea::make('home_care_notes')->rows(2)->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Treatment items')->schema([
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('applyStandard')
                        ->label('Apply standard consult')->icon('heroicon-m-square-3-stack-3d')
                        ->form([
                            Forms\Components\Select::make('standard_consult_id')->label('Standard consult')->required()
                                ->options(StandardConsult::where('is_active', true)->pluck('name', 'id')),
                        ])
                        ->action(function (array $data, Forms\Set $set, Forms\Get $get) {
                            $std = StandardConsult::with('items.product')->find($data['standard_consult_id']);
                            $lines = collect($get('items') ?? []);
                            foreach ($std->items as $sItem) {
                                $p = $sItem->product;
                                $lines->push(self::lineFromProduct($p, $sItem->kind, (float) $sItem->qty));
                            }
                            $set('items', $lines->values()->all());
                        }),
                ]),
                Forms\Components\Repeater::make('items')->relationship()->columnSpanFull()
                    ->schema([
                        Forms\Components\Select::make('kind')->options([
                            'service' => 'Service', 'drug' => 'Drug', 'vaccination' => 'Vaccination', 'misc' => 'Misc',
                        ])->required()->live()->default('service'),
                        Forms\Components\Select::make('product_id')->label('Item')
                            ->options(fn (Forms\Get $get) => Product::query()
                                ->when($get('kind') === 'service', fn ($q) => $q->where('kind', 'service'))
                                ->when($get('kind') === 'drug', fn ($q) => $q->where('kind', 'product'))
                                ->when($get('kind') === 'vaccination', fn ($q) => $q->where('kind', 'vaccine'))
                                ->when($get('kind') === 'misc', fn ($q) => $q->whereRaw('1 = 0'))
                                ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live()->visible(fn (Forms\Get $get) => $get('kind') !== 'misc')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (! $p = Product::find($state)) {
                                    return;
                                }
                                $line = self::lineFromProduct($p, $get('kind'), (float) ($get('qty') ?: 1));
                                foreach ($line as $k => $v) {
                                    $set($k, $v);
                                }
                            }),
                        Forms\Components\TextInput::make('description')->required(),
                        Forms\Components\TextInput::make('qty')->numeric()->default(1)->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('unit_price_ex_tax')->label('Unit price (ex tax)')->numeric()->prefix('₱')->required(),
                        Forms\Components\TextInput::make('discount_pct')->label('Disc %')->numeric()->default(0),
                        Forms\Components\TextInput::make('tax_rate')->label('Tax %')->numeric()->default(12),
                        Forms\Components\TextInput::make('dispensing_fee')->numeric()->prefix('₱')->default(0)
                            ->visible(fn (Forms\Get $get) => $get('kind') === 'drug'),
                        Forms\Components\TextInput::make('injection_fee')->numeric()->prefix('₱')->default(0)
                            ->visible(fn (Forms\Get $get) => $get('kind') === 'vaccination'),
                        Forms\Components\Select::make('regime_id')->label('Regime')
                            ->relationship('regime', 'name')->searchable()->preload()
                            ->visible(fn (Forms\Get $get) => $get('kind') === 'drug'),
                        Forms\Components\TextInput::make('drug_regime')->label('Directions')
                            ->visible(fn (Forms\Get $get) => $get('kind') === 'drug'),
                    ])->columns(4)->addActionLabel('Add item')->defaultItems(0)
                    ->disabled(fn (?Consultation $record) => $record?->isFinalized() && ! Auth::user()->can('reopen_consultation')),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    public static function lineFromProduct(Product $product, string $kind, float $qty = 1): array
    {
        $settings = CompanySetting::current();

        return [
            'kind' => $kind,
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => $qty,
            'unit_price_ex_tax' => $product->sell_price_ex_tax,
            'tax_rate' => $product->tax_rate,
            'discount_pct' => 0,
            'dispensing_fee' => $kind === 'drug' && $product->dispense_fee_always ? $product->dispense_fee : 0,
            'injection_fee' => $kind === 'vaccination' ? (float) $settings->default_injection_fee : 0,
            'regime_id' => $kind === 'drug' ? $product->regime_id : null,
            'drug_regime' => $kind === 'drug' ? $product->regime?->name : null,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('consult_date')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client.full_name')->label('Client')->searchable(['surname']),
                Tables\Columns\TextColumn::make('provider.name')->label('Provider')->toggleable(),
                Tables\Columns\TextColumn::make('reason.reason')->label('Reason')->toggleable(),
                Tables\Columns\TextColumn::make('consult_diagnosis')->label('Diagnosis')->limit(30)->toggleable(),
                Tables\Columns\TextColumn::make('total_inc_tax')->label('Total')->money('PHP')->alignEnd(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'open', 'info' => 'closed', 'success' => 'finalized',
                ]),
            ])
            ->defaultSort('consult_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open', 'closed' => 'Closed', 'finalized' => 'Finalized',
                ]),
                Tables\Filters\SelectFilter::make('provider_id')->relationship('provider', 'name')->label('Provider'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('finalize')
                    ->label('Finalize')->icon('heroicon-m-lock-closed')->color('success')->requiresConfirmation()
                    ->modalDescription('Locks the consult, deducts stock for drugs and vaccines, registers vaccinations and raises the invoice.')
                    ->visible(fn (Consultation $r) => $r->status !== 'finalized' && Auth::user()->can('finalize_consultation'))
                    ->action(function (Consultation $r) {
                        $invoice = $r->finalize();
                        Notification::make()->title("Finalized — invoice {$invoice->invoice_no} for ₱".number_format($invoice->total, 2))->success()->send();
                    }),
                Tables\Actions\Action::make('reopen')
                    ->label('Re-open')->icon('heroicon-m-lock-open')->color('gray')->requiresConfirmation()
                    ->visible(fn (Consultation $r) => $r->status === 'finalized' && Auth::user()->can('reopen_consultation'))
                    ->action(fn (Consultation $r) => $r->update(['status' => 'closed'])),
                Tables\Actions\Action::make('invoice')
                    ->label('Invoice')->icon('heroicon-m-document-text')->color('gray')
                    ->visible(fn (Consultation $r) => $r->invoice()->exists()
                        && \Illuminate\Support\Facades\Route::has('filament.admin.resources.invoices.view'))
                    ->url(fn (Consultation $r) => InvoiceResource::getUrl('view', ['record' => $r->invoice->id])),
                Tables\Actions\Action::make('certificate')
                    ->label('Certificate')->icon('heroicon-m-document-arrow-down')->color('gray')
                    ->visible(fn (Consultation $r) => $r->vaccinations()->exists() && \Illuminate\Support\Facades\Route::has('consultations.certificate'))
                    ->url(fn (Consultation $r) => route('consultations.certificate', $r))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsultations::route('/'),
            'create' => Pages\CreateConsultation::route('/create'),
            'edit' => Pages\EditConsultation::route('/{record}/edit'),
        ];
    }
}
