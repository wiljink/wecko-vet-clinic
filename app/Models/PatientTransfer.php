<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTransfer extends Model
{
    protected $fillable = [
        'patient_id', 'from_client_id', 'to_client_id', 'transferred_by', 'reason', 'transferred_at',
    ];

    protected $casts = ['transferred_at' => 'datetime'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'from_client_id');
    }

    public function toClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'to_client_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
