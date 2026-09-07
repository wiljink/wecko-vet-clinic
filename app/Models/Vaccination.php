<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vaccination extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'patient_id', 'consultation_id', 'product_id', 'name', 'batch_no',
        'given_on', 'provider_id', 'booster_due_on', 'protection', 'certificate_printed',
    ];

    protected $casts = [
        'given_on' => 'date',
        'booster_due_on' => 'date',
        'certificate_printed' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
