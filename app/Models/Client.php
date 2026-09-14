<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'title_id', 'given_name', 'middle_name', 'surname', 'company_name',
        'use_company_as_first_address', 'partner_name', 'email',
        'residence_phone', 'office_phone', 'office_ext', 'mobile_phone', 'fax',
        'account_name_reference_id', 'referral_id', 'payment_type', 'statement_type',
        'discount_pct', 'account_opened_on', 'notes',
        'appointments_by_letter', 'appointments_by_email', 'appointments_by_sms',
        'reminders_by_letter', 'reminders_by_email', 'reminders_by_sms',
        'marketing_by_letter', 'marketing_by_email', 'marketing_by_sms',
        'is_active',
    ];

    protected $casts = [
        'use_company_as_first_address' => 'boolean',
        'account_opened_on' => 'date',
        'discount_pct' => 'decimal:2',
        'is_active' => 'boolean',
        'appointments_by_letter' => 'boolean',
        'appointments_by_email' => 'boolean',
        'appointments_by_sms' => 'boolean',
        'reminders_by_letter' => 'boolean',
        'reminders_by_email' => 'boolean',
        'reminders_by_sms' => 'boolean',
        'marketing_by_letter' => 'boolean',
        'marketing_by_email' => 'boolean',
        'marketing_by_sms' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->account_opened_on ??= now()->toDateString();
        });
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function accountReference(): BelongsTo
    {
        return $this->belongsTo(AccountNameReference::class, 'account_name_reference_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountAdjustments(): HasMany
    {
        return $this->hasMany(AccountAdjustment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /**
     * Every prescription ever dispensed across all of this client's patients —
     * lets a vet see the full medication history for the account in one place.
     */
    public function prescriptions(): HasManyThrough
    {
        return $this->hasManyThrough(Prescription::class, Patient::class);
    }

    public function vaccinations(): HasManyThrough
    {
        return $this->hasManyThrough(Vaccination::class, Patient::class);
    }

    public function primaryAddress(): ?ClientAddress
    {
        return $this->addresses->firstWhere('is_primary', true) ?? $this->addresses->first();
    }

    public function getFullNameAttribute(): string
    {
        if ($this->company_name && $this->use_company_as_first_address) {
            return $this->company_name;
        }

        $parts = array_filter([
            $this->title?->name,
            $this->given_name,
            $this->surname,
        ]);

        return trim(implode(' ', $parts)) ?: $this->surname;
    }

    /**
     * Outstanding account balance = unpaid invoices + debit adjustments
     * − credit adjustments − unapplied advance payments.
     * Returns 0 until the billing tables exist (built in a later phase).
     */
    public function currentBalance(): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('invoices')) {
            return 0.0;
        }

        $invoiceBalance = (float) $this->invoices()->where('status', '!=', 'void')->sum('balance');

        $adjustments = (float) $this->accountAdjustments()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0) AS net")
            ->value('net');

        $unappliedAdvance = (float) $this->payments()
            ->where('payment_type', 'advance_payment')
            ->where('is_refund', false)
            ->get()
            ->sum(fn (Payment $p) => (float) $p->amount - (float) $p->allocations()->sum('amount'));

        return round($invoiceBalance + $adjustments - $unappliedAdvance, 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('surname', 'like', "%{$term}%")
            ->orWhere('given_name', 'like', "%{$term}%")
            ->orWhere('company_name', 'like', "%{$term}%")
            ->orWhere('mobile_phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%"));
    }
}
