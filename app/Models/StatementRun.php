<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class StatementRun extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'from_date', 'to_date', 'min_balance', 'fee_type', 'fee_value',
        'exclude_no_activity', 'channel', 'statement_count', 'generated_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'min_balance' => 'decimal:2',
        'fee_value' => 'decimal:2',
        'exclude_no_activity' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->generated_by ??= auth()->id());
    }

    /** The bookkeeping / late-payment fee for a given outstanding balance. */
    public function feeFor(float $balance): float
    {
        return match ($this->fee_type) {
            'fixed' => (float) $this->fee_value,
            'percent' => round($balance * (float) $this->fee_value / 100, 2),
            default => 0.0,
        };
    }
}
