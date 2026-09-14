<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class TillSession extends Model
{
    use BelongsToLocation, RecordsActivity;

    protected $fillable = [
        'session_date', 'location_id', 'opening_float', 'denominations',
        'expected_cash', 'counted_cash', 'variance', 'status', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'denominations' => 'array',
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    /** Cash the system expects in the drawer = opening float + this branch's cash payments today. */
    public function expectedCash(): float
    {
        $cashToday = Payment::where('payment_type', 'cash')
            ->where('location_id', $this->location_id)
            ->where('is_refund', false)
            ->whereDate('received_at', $this->session_date)
            ->sum('amount');

        $refundsToday = Payment::where('payment_type', 'cash')
            ->where('location_id', $this->location_id)
            ->where('is_refund', true)
            ->whereDate('received_at', $this->session_date)
            ->sum('amount');

        return round((float) $this->opening_float + (float) $cashToday - (float) $refundsToday, 2);
    }

    public function countedFromDenominations(): float
    {
        return collect($this->denominations ?? [])
            ->sum(fn ($count, $denom) => (float) $denom * (int) $count);
    }

    public function close(): void
    {
        $counted = $this->countedFromDenominations() ?: (float) $this->counted_cash;
        $expected = $this->expectedCash();

        $this->update([
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'variance' => round($counted - $expected, 2),
            'status' => 'closed',
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);
    }
}
