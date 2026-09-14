<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToLocation;

    public const TYPES = [
        'opening' => 'Opening balance',
        'receipt' => 'Stock receipt',
        'sale' => 'Sale / consult',
        'vaccine_consumable' => 'Vaccine consumable',
        'adjustment' => 'Adjustment',
        'stock_take' => 'Stock take',
        'return_supplier' => 'Return to supplier',
        'return_customer' => 'Customer return',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
    ];

    protected $fillable = [
        'product_id', 'location_id', 'type', 'qty_change', 'unit_cost_ex_tax', 'source_type', 'source_id',
        'batch_no', 'expiry_on', 'reason', 'balance_after', 'moved_at', 'created_by',
    ];

    protected $casts = [
        'qty_change' => 'decimal:2',
        'unit_cost_ex_tax' => 'decimal:4',
        'balance_after' => 'decimal:2',
        'expiry_on' => 'date',
        'moved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            $movement->moved_at ??= now();
            $movement->created_by ??= auth()->id();

            if (! $movement->location_id) {
                throw new \InvalidArgumentException('StockMovement::record() requires a location_id.');
            }

            $level = ProductStockLevel::for($movement->product, $movement->location_id);
            $movement->balance_after = round((float) $level->qty_on_hand + (float) $movement->qty_change, 2);
        });

        static::created(function (StockMovement $movement) {
            ProductStockLevel::for($movement->product, $movement->location_id)
                ->update(['qty_on_hand' => $movement->balance_after]);

            Product::whereKey($movement->product_id)->update([
                'qty_on_hand' => ProductStockLevel::where('product_id', $movement->product_id)->sum('qty_on_hand'),
            ]);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Record a stock change and update the product's cached quantity.
     * The single entry point every workflow should use.
     */
    public static function record(Product $product, string $type, float $qtyChange, array $attributes = []): self
    {
        return $product->stockMovements()->create(array_merge([
            'type' => $type,
            'qty_change' => $qtyChange,
            'unit_cost_ex_tax' => $attributes['unit_cost_ex_tax'] ?? $product->unit_cost_ex_tax,
        ], $attributes));
    }
}
