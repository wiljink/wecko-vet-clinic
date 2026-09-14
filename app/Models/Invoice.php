<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use BelongsToLocation, GeneratesReference, RecordsActivity;

    protected string $referenceColumn = 'invoice_no';

    protected string $referencePrefix = 'INV';

    protected $fillable = [
        'invoice_no', 'client_id', 'patient_id', 'location_id', 'source_type', 'source_id',
        'invoice_date', 'due_date', 'subtotal_ex_tax', 'discount_total', 'tax_total',
        'total', 'amount_paid', 'balance', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal_ex_tax' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $i) {
            $i->created_by ??= auth()->id();
            $i->invoice_date ??= now()->toDateString();
            $i->due_date ??= now()->addDays(CompanySetting::current()->accounting_period_days ?: 30)->toDateString();
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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function recalculateTotals(): void
    {
        $ex = (float) $this->items()->sum('line_total_ex_tax');
        $tax = (float) $this->items()->sum('line_tax');
        $discount = (float) $this->items()->sum(\Illuminate\Support\Facades\DB::raw('qty * unit_price_ex_tax * discount_pct / 100'));
        $paid = (float) $this->allocations()->sum('amount');
        $total = round($ex + $tax, 2);

        $this->forceFill([
            'subtotal_ex_tax' => $ex,
            'discount_total' => round($discount, 2),
            'tax_total' => $tax,
            'total' => $total,
            'amount_paid' => $paid,
            'balance' => round($total - $paid, 2),
            'status' => $this->status === 'void' ? 'void' : match (true) {
                $paid <= 0 => 'unpaid',
                $paid + 0.001 >= $total => 'paid',
                default => 'partial',
            },
        ])->saveQuietly();
    }

    /** Build an invoice from a completed Consultation or CounterSale. */
    public static function fromSource(Model $source, array $lines): self
    {
        $invoice = static::create([
            'client_id' => $source->client_id,
            'patient_id' => $source->patient_id ?? null,
            'location_id' => $source->location_id ?? null,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'invoice_date' => ($source->consult_date ?? $source->sale_date ?? now()),
        ]);

        foreach ($lines as $line) {
            $invoice->items()->create($line);
        }

        $invoice->recalculateTotals();

        return $invoice;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }
}
