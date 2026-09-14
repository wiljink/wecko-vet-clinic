<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use App\Support\LocationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Not branch-restricted like other transactional models: a batch can bundle
 * payments across every branch a principal can see, so it stays visible
 * to whoever has banking access regardless of the acting branch filter.
 */
class BankingBatch extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'BNK';

    protected $fillable = [
        'reference', 'banking_date', 'location_id', 'cash_total', 'cheque_total', 'eftpos_total',
        'card_total', 'grand_total', 'notes', 'created_by',
    ];

    protected $casts = [
        'banking_date' => 'date',
        'cash_total' => 'decimal:2',
        'cheque_total' => 'decimal:2',
        'eftpos_total' => 'decimal:2',
        'card_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $b) => $b->created_by ??= auth()->id());
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Bank every un-banked payment up to and including the given date. */
    public static function bankUpTo(\Illuminate\Support\Carbon $date): self
    {
        return DB::transaction(function () use ($date) {
            $payments = Payment::where('banked', false)
                ->where('is_refund', false)
                ->whereDate('received_at', '<=', $date)
                ->get();

            $batch = static::create([
                'banking_date' => $date->toDateString(),
                'location_id' => LocationContext::activeId(),
                'cash_total' => $payments->where('payment_type', 'cash')->sum('amount'),
                'cheque_total' => $payments->where('payment_type', 'cheque')->sum('amount'),
                'eftpos_total' => $payments->where('payment_type', 'eftpos')->sum('amount'),
                'card_total' => $payments->where('payment_type', 'credit_card')->sum('amount'),
            ]);
            $batch->update(['grand_total' => $payments->sum('amount')]);

            Payment::whereIn('id', $payments->pluck('id'))->update([
                'banked' => true,
                'banked_on' => $date->toDateString(),
                'banking_batch_id' => $batch->id,
            ]);

            return $batch;
        });
    }
}
