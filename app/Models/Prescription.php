<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'patient_id', 'consultation_id', 'product_id', 'drug_name', 'regime_id',
        'dosage', 'quantity', 'dispensing_fee', 'start_on', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'dispensing_fee' => 'decimal:2',
        'start_on' => 'date',
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

    public function regime(): BelongsTo
    {
        return $this->belongsTo(RegimeType::class, 'regime_id');
    }
}
