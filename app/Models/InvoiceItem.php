<?php

namespace App\Models;

use App\Support\LineTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'kind', 'description', 'qty', 'unit_price_ex_tax',
        'tax_rate', 'discount_pct', 'dispensing_fee', 'line_total_ex_tax', 'line_tax', 'line_total_inc_tax',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price_ex_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'dispensing_fee' => 'decimal:2',
        'line_total_ex_tax' => 'decimal:2',
        'line_tax' => 'decimal:2',
        'line_total_inc_tax' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $t = LineTotals::forLine(
                (float) $item->qty, (float) $item->unit_price_ex_tax, (float) $item->tax_rate,
                (float) $item->discount_pct, (float) $item->dispensing_fee,
            );
            $item->line_total_ex_tax = $t['ex_tax'];
            $item->line_tax = $t['tax'];
            $item->line_total_inc_tax = $t['inc_tax'];
        });

        static::saved(fn (self $i) => $i->invoice?->recalculateTotals());
        static::deleted(fn (self $i) => $i->invoice?->recalculateTotals());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
