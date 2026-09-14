<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-branch quantity on hand for a product — the source of truth StockMovement writes to. */
class ProductStockLevel extends Model
{
    protected $fillable = ['product_id', 'location_id', 'qty_on_hand'];

    protected $casts = ['qty_on_hand' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public static function for(Product $product, int $locationId): self
    {
        return static::firstOrCreate([
            'product_id' => $product->id,
            'location_id' => $locationId,
        ]);
    }

    public static function qtyOf(Product $product, int $locationId): float
    {
        return (float) (static::where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->value('qty_on_hand') ?? 0);
    }
}
