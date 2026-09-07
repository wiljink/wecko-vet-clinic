<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InventoryReturn extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'RET';

    protected $fillable = [
        'reference', 'direction', 'supplier_id', 'client_id', 'source_document',
        'return_date', 'reason', 'status', 'refund_amount', 'posted_at', 'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'posted_at' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->created_by ??= auth()->id());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReturnItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function recalculateTotal(): void
    {
        $this->update(['refund_amount' => $this->items()->sum(DB::raw('qty * unit_price'))]);
    }

    /**
     * Post the return: stock leaves for a supplier return, comes back for a
     * customer return. Customer returns also raise a refund Payment.
     */
    public function post(): void
    {
        if ($this->isPosted()) {
            return;
        }

        DB::transaction(function () {
            $type = $this->direction === 'supplier' ? 'return_supplier' : 'return_customer';

            foreach ($this->items()->with('product')->get() as $item) {
                $qtyChange = $this->direction === 'supplier' ? -(float) $item->qty : (float) $item->qty;

                StockMovement::record($item->product, $type, $qtyChange, [
                    'reason' => "Return {$this->reference}".($this->source_document ? " ({$this->source_document})" : ''),
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->return_date,
                ]);
            }

            if ($this->direction === 'customer' && $this->client && $this->refund_amount > 0) {
                Payment::create([
                    'client_id' => $this->client_id,
                    'payment_type' => 'cash',
                    'amount' => $this->refund_amount,
                    'is_refund' => true,
                    'reference' => "Refund for return {$this->reference}",
                    'received_at' => now(),
                    'received_by' => auth()->id(),
                ]);
            }

            $this->update(['status' => 'posted', 'posted_at' => now()]);
        });
    }
}
