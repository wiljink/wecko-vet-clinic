<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use BelongsToLocation, RecordsActivity;

    protected $fillable = [
        'client_id', 'patient_id', 'provider_id', 'location_id', 'room_id',
        'starts_at', 'ends_at', 'duration_minutes', 'all_day',
        'appointment_reason_id', 'appointment_label_id', 'appointment_status_id', 'notes',
        'recurrence_freq', 'recurrence_interval', 'recurrence_weekdays',
        'recurrence_until', 'recurrence_count', 'recurrence_parent_id',
        'reminder_channel', 'reminder_sent_at', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'recurrence_weekdays' => 'array',
        'recurrence_until' => 'date',
        'reminder_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->created_by ??= auth()->id());

        static::saving(function (self $a) {
            if ($a->starts_at && ! $a->ends_at) {
                $a->ends_at = $a->starts_at->copy()->addMinutes($a->duration_minutes ?: 15);
            }
            if ($a->starts_at && $a->ends_at) {
                $a->duration_minutes = max(1, $a->starts_at->diffInMinutes($a->ends_at));
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'room_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AppointmentReason::class, 'appointment_reason_id');
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(AppointmentLabel::class, 'appointment_label_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class, 'appointment_status_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function consultation(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /** Calendar colour: label wins, otherwise status. */
    public function getColorAttribute(): string
    {
        return $this->label?->color ?? $this->status?->color ?? '#0d9488';
    }

    /**
     * Generate concrete child appointments for a recurrence rule.
     * Capped at 60 occurrences to keep the calendar sane.
     */
    public function generateOccurrences(): int
    {
        if (! $this->recurrence_freq || $this->recurrence_parent_id) {
            return 0;
        }

        $this->occurrences()->delete();

        $cursor = $this->starts_at->copy();
        $end = $this->recurrence_until
            ? Carbon::parse($this->recurrence_until)->endOfDay()
            : $this->starts_at->copy()->addYear();
        $limit = min($this->recurrence_count ?? 60, 60);
        $made = 0;

        while ($made < $limit) {
            $cursor = $this->advance($cursor);

            if ($cursor->gt($end)) {
                break;
            }

            if ($this->recurrence_freq === 'weekday' && $cursor->isWeekend()) {
                continue;
            }

            $this->occurrences()->create([
                'client_id' => $this->client_id,
                'patient_id' => $this->patient_id,
                'provider_id' => $this->provider_id,
                'location_id' => $this->location_id,
                'room_id' => $this->room_id,
                'starts_at' => $cursor,
                'ends_at' => $cursor->copy()->addMinutes($this->duration_minutes),
                'duration_minutes' => $this->duration_minutes,
                'all_day' => $this->all_day,
                'appointment_reason_id' => $this->appointment_reason_id,
                'appointment_label_id' => $this->appointment_label_id,
                'appointment_status_id' => $this->appointment_status_id,
                'notes' => $this->notes,
            ]);
            $made++;
        }

        return $made;
    }

    private function advance(Carbon $date): Carbon
    {
        $n = max(1, $this->recurrence_interval);

        return match ($this->recurrence_freq) {
            'daily', 'weekday' => $date->copy()->addDays($this->recurrence_freq === 'weekday' ? 1 : $n),
            'weekly' => $date->copy()->addWeeks($n),
            'monthly' => $date->copy()->addMonthsNoOverflow($n),
            'yearly' => $date->copy()->addYears($n),
            default => $date->copy()->addYears(100),
        };
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
