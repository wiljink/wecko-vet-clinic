<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReturnItem extends Model
{
    protected $fillable = ['inventory_return_id', 'product_id', 'qty', 'unit_price'];

    protected $casts = ['qty' => 'decimal:2', 'unit_price' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saved(fn (self $i) => $i->inventoryReturn?->recalculateTotal());
        static::deleted(fn (self $i) => $i->inventoryReturn?->recalculateTotal());
    }

    public function inventoryReturn(): BelongsTo
    {
        return $this->belongsTo(InventoryReturn::class, 'inventory_return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
