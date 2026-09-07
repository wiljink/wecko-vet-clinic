<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use RecordsActivity;

    public const CATEGORIES = [
        'vaccination' => 'Vaccination',
        'desexing' => 'De-sexing',
        'other' => 'Other',
        'phone' => 'Phone call',
    ];

    protected $fillable = [
        'remindable_type', 'remindable_id', 'client_id', 'patient_id',
        'patient_reminder_type_id', 'category', 'vaccination_id', 'species_id',
        'due_on', 'notes', 'status', 'sent_channel', 'sent_at', 'batch_id', 'sequence',
    ];

    protected $casts = [
        'due_on' => 'date',
        'sent_at' => 'datetime',
        'sequence' => 'integer',
    ];

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reminderType(): BelongsTo
    {
        return $this->belongsTo(PatientReminderType::class, 'patient_reminder_type_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function scopeDue(Builder $query, $on = null): Builder
    {
        return $query->where('status', 'pending')->whereDate('due_on', '<=', $on ?? now());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
