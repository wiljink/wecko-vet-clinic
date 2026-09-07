<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

class Consultation extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'client_id', 'patient_id', 'provider_id', 'location_id', 'appointment_id',
        'appointment_reason_id', 'consult_date', 'weight', 'temperature',
        'history', 'examination', 'tests', 'comment',
        'differential_diagnosis', 'consult_diagnosis', 'treatment', 'home_care_notes',
        'status', 'subtotal_ex_tax', 'discount_total', 'tax_total', 'total_inc_tax',
        'finalized_at', 'finalized_by',
    ];

    protected $casts = [
        'consult_date' => 'datetime',
        'weight' => 'decimal:2',
        'temperature' => 'decimal:2',
        'subtotal_ex_tax' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_inc_tax' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->consult_date ??= now();
            $settings = CompanySetting::current();
            foreach (['history', 'examination', 'tests', 'differential_diagnosis', 'consult_diagnosis'] as $field) {
                $c->{$field} ??= $settings->{"default_{$field}"} ?: null;
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AppointmentReason::class, 'appointment_reason_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsultationItem::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'source');
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function recalculateTotals(): void
    {
        $this->forceFill([
            'subtotal_ex_tax' => (float) $this->items()->sum('line_total_ex_tax'),
            'tax_total' => (float) $this->items()->sum('line_tax'),
            'total_inc_tax' => (float) $this->items()->sum('line_total_inc_tax'),
            'discount_total' => (float) $this->items()->sum(
                DB::raw('qty * unit_price_ex_tax * discount_pct / 100')
            ),
        ])->saveQuietly();
    }

    /**
     * Finalize the consult (manual 6.4.5): lock it, deduct stock for every drug /
     * vaccine line and its vaccine consumables, register vaccinations (with a
     * booster reminder + certificate flag), record prescriptions, and raise the
     * invoice.
     */
    public function finalize(): Invoice
    {
        if ($this->isFinalized() && $this->invoice) {
            return $this->invoice;
        }

        return DB::transaction(function () {
            $this->recalculateTotals();
            $invoiceLines = [];

            foreach ($this->items()->with('product')->get() as $item) {
                $invoiceLines[] = [
                    'product_id' => $item->product_id,
                    'kind' => $item->kind,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'unit_price_ex_tax' => $item->unit_price_ex_tax,
                    'tax_rate' => $item->tax_rate,
                    'discount_pct' => $item->discount_pct,
                    'dispensing_fee' => $item->dispensing_fee + $item->injection_fee,
                ];

                $product = $item->product;
                if (! $product) {
                    continue;
                }

                if (in_array($item->kind, ['drug', 'vaccination'], true) && $product->tracksStock()) {
                    StockMovement::record($product, 'sale', -(float) $item->qty, [
                        'reason' => "Consult #{$this->id}",
                        'source_type' => $this->getMorphClass(),
                        'source_id' => $this->id,
                        'moved_at' => $this->consult_date,
                    ]);
                }

                if ($item->kind === 'vaccination') {
                    $this->registerVaccination($item, $product);
                }

                if ($item->kind === 'drug') {
                    $this->prescriptions()->create([
                        'patient_id' => $this->patient_id,
                        'product_id' => $product->id,
                        'drug_name' => $product->name,
                        'regime_id' => $item->regime_id,
                        'dosage' => $item->drug_regime,
                        'quantity' => $item->qty,
                        'dispensing_fee' => $item->dispensing_fee,
                        'start_on' => $this->consult_date->toDateString(),
                    ]);
                }
            }

            $invoice = Invoice::fromSource($this, $invoiceLines);

            $this->patient?->update(['last_visit_on' => $this->consult_date->toDateString()]);
            $this->forceFill([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ])->saveQuietly();

            return $invoice;
        });
    }

    private function registerVaccination(ConsultationItem $item, Product $vaccine): void
    {
        $booster = null;
        if ($vaccine->booster_years || $vaccine->booster_months || $vaccine->booster_days) {
            $booster = $this->consult_date->copy()
                ->addYears($vaccine->booster_years)
                ->addMonths($vaccine->booster_months)
                ->addDays($vaccine->booster_days)
                ->toDateString();
        }

        $vaccination = $this->vaccinations()->create([
            'patient_id' => $this->patient_id,
            'product_id' => $vaccine->id,
            'name' => $vaccine->name,
            'given_on' => $this->consult_date->toDateString(),
            'provider_id' => $this->provider_id,
            'booster_due_on' => $booster,
            'protection' => $vaccine->protection,
        ]);

        // Auto-deduct needles / swabs / gloves used per dose.
        foreach ($vaccine->consumables as $consumable) {
            if ($consumable->tracksStock()) {
                StockMovement::record($consumable, 'vaccine_consumable', -(float) $consumable->pivot->qty, [
                    'reason' => "Vaccine {$vaccine->name} — consult #{$this->id}",
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->consult_date,
                ]);
            }
        }

        if ($booster) {
            Reminder::create([
                'remindable_type' => $vaccination->getMorphClass(),
                'remindable_id' => $vaccination->id,
                'client_id' => $this->client_id,
                'patient_id' => $this->patient_id,
                'patient_reminder_type_id' => $vaccine->patient_reminder_type_id,
                'category' => 'vaccination',
                'vaccination_id' => $vaccination->id,
                'species_id' => $this->patient?->species_id,
                'due_on' => $booster,
                'notes' => "{$vaccine->name} booster due",
            ]);
        }
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
