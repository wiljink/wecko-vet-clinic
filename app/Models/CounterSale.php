<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

class CounterSale extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referenceColumn = 'sale_no';

    protected string $referencePrefix = 'CS';

    protected $fillable = [
        'sale_no', 'client_id', 'walk_in', 'walk_in_name', 'provider_id', 'location_id',
        'sale_date', 'status', 'subtotal_ex_tax', 'discount_total', 'tax_total',
        'total_inc_tax', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'walk_in' => 'boolean',
        'sale_date' => 'date',
        'completed_at' => 'datetime',
        'subtotal_ex_tax' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_inc_tax' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->created_by ??= auth()->id();
            $s->sale_date ??= now()->toDateString();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CounterSaleItem::class);
    }

    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'source');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'on_account'], true);
    }

    public function recalculateTotals(): void
    {
        $this->forceFill([
            'subtotal_ex_tax' => (float) $this->items()->sum('line_total_ex_tax'),
            'tax_total' => (float) $this->items()->sum('line_tax'),
            'total_inc_tax' => (float) $this->items()->sum('line_total_inc_tax'),
            'discount_total' => (float) $this->items()->sum(DB::raw('qty * unit_price_ex_tax * discount_pct / 100')),
        ])->saveQuietly();
    }

    /**
     * Complete the sale: deduct stock, raise an invoice, and (unless left on
     * account) take a payment that settles it. Returns [Invoice, ?Payment].
     */
    public function complete(?array $payment = null): array
    {
        if ($this->isCompleted() && $this->invoice) {
            return [$this->invoice, null];
        }

        return DB::transaction(function () use ($payment) {
            $this->recalculateTotals();

            abort_if($this->walk_in === false && ! $this->client_id, 422, 'Select a client or mark as walk-in.');

            $lines = [];
            foreach ($this->items()->with('product')->get() as $item) {
                $lines[] = [
                    'product_id' => $item->product_id,
                    'kind' => $item->kind === 'product' ? 'drug' : 'misc',
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'unit_price_ex_tax' => $item->unit_price_ex_tax,
                    'tax_rate' => $item->tax_rate,
                    'discount_pct' => $item->discount_pct,
                    'dispensing_fee' => $item->dispensing_fee,
                ];

                if ($item->product?->tracksStock()) {
                    StockMovement::record($item->product, 'sale', -(float) $item->qty, [
                        'reason' => "Counter sale {$this->sale_no}",
                        'source_type' => $this->getMorphClass(),
                        'source_id' => $this->id,
                        'moved_at' => $this->sale_date,
                    ]);
                }
            }

            // A walk-in with no client uses the "Counter" pseudo-client.
            $clientId = $this->client_id ?? Client::firstOrCreate(
                ['surname' => 'Counter Sales'],
                ['given_name' => 'Walk-in', 'is_active' => true, 'statement_type' => 'none'],
            )->id;

            $invoice = Invoice::create([
                'client_id' => $clientId,
                'source_type' => $this->getMorphClass(),
                'source_id' => $this->id,
                'invoice_date' => $this->sale_date,
            ]);
            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }
            $invoice->recalculateTotals();

            $paymentModel = null;
            if ($payment) {
                $paymentModel = Payment::create([
                    'client_id' => $clientId,
                    'payment_type' => $payment['payment_type'],
                    'amount' => $invoice->total,
                    'cash_received' => $payment['cash_received'] ?? null,
                    'card_type_id' => $payment['card_type_id'] ?? null,
                    'reference' => $payment['reference'] ?? "Counter sale {$this->sale_no}",
                    'received_at' => now(),
                ]);
                $paymentModel->allocateTo([$invoice]);
                $this->update(['status' => 'completed', 'completed_at' => now()]);
            } else {
                $this->update(['status' => 'on_account', 'completed_at' => now()]);
            }

            return [$invoice->fresh(), $paymentModel];
        });
    }
}
