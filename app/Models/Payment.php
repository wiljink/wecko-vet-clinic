<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToLocation, GeneratesReference, RecordsActivity;

    protected string $referenceColumn = 'payment_no';

    protected string $referencePrefix = 'PMT';

    public const TYPES = [
        'cash' => 'Cash',
        'credit_card' => 'Credit card',
        'cheque' => 'Cheque',
        'eftpos' => 'EFTPOS',
        'advance_payment' => 'Advance payment',
    ];

    protected $fillable = [
        'payment_no', 'client_id', 'location_id', 'payment_type', 'amount', 'cash_received', 'change_given',
        'card_type_id', 'reference', 'is_refund', 'banked', 'banked_on', 'banking_batch_id',
        'received_at', 'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_given' => 'decimal:2',
        'is_refund' => 'boolean',
        'banked' => 'boolean',
        'banked_on' => 'date',
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->received_by ??= auth()->id();
            $p->received_at ??= now();

            if ($p->payment_type === 'cash' && $p->cash_received !== null) {
                $p->change_given = max(0, round((float) $p->cash_received - (float) $p->amount, 2));
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getUnallocatedAttribute(): float
    {
        return round((float) $this->amount - (float) $this->allocations()->sum('amount'), 2);
    }

    /** Apply this payment across the given invoices in order, oldest first. */
    public function allocateTo(iterable $invoices): void
    {
        $remaining = $this->unallocated;

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, (float) $invoice->balance);
            if ($apply <= 0) {
                continue;
            }

            $allocation = $this->allocations()->firstOrNew(['invoice_id' => $invoice->id]);
            $allocation->amount = round((float) $allocation->amount + $apply, 2);
            $allocation->save();

            $invoice->recalculateTotals();
            $remaining = round($remaining - $apply, 2);
        }
    }
}
