<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    protected $fillable = ['stock_take_id', 'product_id', 'system_qty', 'counted_qty'];

    protected $casts = ['system_qty' => 'decimal:2', 'counted_qty' => 'decimal:2'];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getVarianceAttribute(): ?float
    {
        return $this->counted_qty === null ? null : (float) $this->counted_qty - (float) $this->system_qty;
    }
}
