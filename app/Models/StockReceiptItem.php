<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptItem extends Model
{
    protected $fillable = [
        'stock_receipt_id', 'product_id', 'qty', 'unit_cost_ex_tax',
        'mark_up', 'sell_price_ex_tax', 'expiry_on', 'batch_no',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost_ex_tax' => 'decimal:4',
        'mark_up' => 'decimal:2',
        'sell_price_ex_tax' => 'decimal:2',
        'expiry_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $i) => $i->receipt?->recalculateTotal());
        static::deleted(fn (self $i) => $i->receipt?->recalculateTotal());
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
