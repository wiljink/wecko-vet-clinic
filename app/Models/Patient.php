<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Patient extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'client_id', 'name', 'microchip_no', 'species_id', 'breed_id', 'colour_id',
        'gender', 'birth_date', 'age_years', 'age_months', 'age_weeks',
        'date_neutered', 'neuter_status', 'weight', 'temperament', 'insurance_policy_no',
        'heart_wormer', 'int_wormer', 'flea_control', 'diet', 'behavioural_warning',
        'first_visit_on', 'last_visit_on', 'photo_path', 'notes', 'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'date_neutered' => 'date',
        'first_visit_on' => 'date',
        'last_visit_on' => 'date',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(PatientTransfer::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Age as a human string, from birth_date if known else the manual y/m/w fields. */
    public function getAgeLabelAttribute(): string
    {
        if ($this->birth_date) {
            $diff = $this->birth_date->diff(Carbon::now());

            return trim(sprintf('%dy %dm', $diff->y, $diff->m)) ?: '< 1m';
        }

        $parts = array_filter([
            $this->age_years ? "{$this->age_years}y" : null,
            $this->age_months ? "{$this->age_months}m" : null,
            $this->age_weeks ? "{$this->age_weeks}w" : null,
        ]);

        return $parts ? implode(' ', $parts) : 'Unknown';
    }

    public function getAgeInMonthsAttribute(): ?int
    {
        if ($this->birth_date) {
            return (int) $this->birth_date->diffInMonths(Carbon::now());
        }

        if ($this->age_years !== null || $this->age_months !== null || $this->age_weeks !== null) {
            return (int) round(($this->age_years ?? 0) * 12 + ($this->age_months ?? 0) + ($this->age_weeks ?? 0) / 4.345);
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
