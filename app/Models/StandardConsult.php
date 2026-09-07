<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardConsult extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'appointment_reason_id', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AppointmentReason::class, 'appointment_reason_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StandardConsultItem::class);
    }
}
