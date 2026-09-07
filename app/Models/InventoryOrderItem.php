<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOrderItem extends Model
{
    protected $fillable = [
        'inventory_order_id', 'product_id', 'qty_ordered', 'unit_cost_ex_tax', 'qty_received',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:2',
        'unit_cost_ex_tax' => 'decimal:4',
        'qty_received' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $i) => $i->order?->recalculateTotal());
        static::deleted(fn (self $i) => $i->order?->recalculateTotal());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(InventoryOrder::class, 'inventory_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getQtyOutstandingAttribute(): float
    {
        return max(0, (float) $this->qty_ordered - (float) $this->qty_received);
    }
}
