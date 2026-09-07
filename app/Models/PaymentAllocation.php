<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saved(fn (self $a) => $a->invoice?->recalculateTotals());
        static::deleted(fn (self $a) => $a->invoice?->recalculateTotals());
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
