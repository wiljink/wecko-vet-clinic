<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockReceipt extends Model
{
    use BelongsToLocation, GeneratesReference, RecordsActivity;

    protected string $referenceColumn = 'receipt_no';

    protected string $referencePrefix = 'SR';

    protected $fillable = [
        'receipt_no', 'supplier_id', 'inventory_order_id', 'location_id', 'received_date', 'status',
        'supplier_doc_no', 'notes', 'total_ex_tax', 'posted_at', 'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'posted_at' => 'datetime',
        'total_ex_tax' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->created_by ??= auth()->id());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class, 'inventory_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total_ex_tax' => $this->items()->sum(DB::raw('qty * unit_cost_ex_tax')),
        ]);
    }

    /**
     * Post the receipt: add stock, update each product's cost/sell price and,
     * where this fulfils an order, advance the order's received quantities.
     */
    public function post(): void
    {
        if ($this->isPosted()) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items()->with('product')->get() as $item) {
                $product = $item->product;

                StockMovement::record($product, 'receipt', (float) $item->qty, [
                    'location_id' => $this->location_id,
                    'unit_cost_ex_tax' => $item->unit_cost_ex_tax,
                    'batch_no' => $item->batch_no,
                    'expiry_on' => $item->expiry_on,
                    'reason' => "Receipt {$this->receipt_no}",
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->received_date,
                ]);

                $product->fill([
                    'unit_cost_ex_tax' => $item->unit_cost_ex_tax,
                    'list_cost_price' => $item->unit_cost_ex_tax,
                ]);
                if ($item->sell_price_ex_tax !== null) {
                    $product->sell_price_ex_tax = $item->sell_price_ex_tax;
                }
                if ($item->mark_up !== null) {
                    $product->mark_up = $item->mark_up;
                }
                $product->save();

                if ($this->order) {
                    $orderItem = $this->order->items()->where('product_id', $product->id)->first();
                    $orderItem?->increment('qty_received', (float) $item->qty);
                }
            }

            $this->order?->refreshStatus();
            $this->update(['status' => 'posted', 'posted_at' => now()]);
        });
    }
}
