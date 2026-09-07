<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOrder extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referenceColumn = 'order_no';

    protected string $referencePrefix = 'PO';

    protected $fillable = [
        'order_no', 'supplier_id', 'order_date', 'delivery_date', 'status',
        'total_ex_tax', 'notes', 'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'total_ex_tax' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $o) => $o->created_by ??= auth()->id());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total_ex_tax' => $this->items()->sum(
                \Illuminate\Support\Facades\DB::raw('qty_ordered * unit_cost_ex_tax')
            ),
        ]);
    }

    public function refreshStatus(): void
    {
        $ordered = (float) $this->items()->sum('qty_ordered');
        $received = (float) $this->items()->sum('qty_received');

        $status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $received <= 0 => $this->status === 'draft' ? 'draft' : 'placed',
            $received >= $ordered => 'received',
            default => 'partially_received',
        };

        $this->update(['status' => $status]);
    }

    /** Build order lines for every product below its reorder level (manual 5.4.1). */
    public static function autoFillFor(Supplier $supplier): self
    {
        $order = static::create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        Product::query()
            ->where('supplier_id', $supplier->id)
            ->belowReorder()
            ->get()
            ->each(function (Product $product) use ($order) {
                $target = max($product->max_holding, $product->reorder_level);
                $qty = max(0, $target - $product->qty_on_hand);

                if ($qty > 0) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'qty_ordered' => $qty,
                        'unit_cost_ex_tax' => $product->unit_cost_ex_tax,
                    ]);
                }
            });

        $order->recalculateTotal();

        return $order;
    }
}
