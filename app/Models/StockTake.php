<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockTake extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'ST';

    protected $fillable = ['reference', 'take_date', 'status', 'notes', 'posted_at', 'created_by'];

    protected $casts = ['take_date' => 'date', 'posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->created_by ??= auth()->id());
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /** Seed a line for every stock-tracked product with the current on-hand snapshot. */
    public function loadAllProducts(): void
    {
        Product::query()->whereIn('kind', ['product', 'vaccine'])->where('is_active', true)
            ->get()
            ->each(fn (Product $p) => $this->items()->firstOrCreate(
                ['product_id' => $p->id],
                ['system_qty' => $p->qty_on_hand],
            ));
    }

    /**
     * Post the take: for every counted line, write an adjustment movement for the
     * variance (manual 5.3.2). Blank counts are skipped — never treated as zero.
     */
    public function post(): void
    {
        if ($this->isPosted()) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items()->with('product')->get() as $item) {
                if ($item->counted_qty === null) {
                    continue;
                }

                $variance = (float) $item->counted_qty - (float) $item->product->qty_on_hand;

                if (abs($variance) < 0.0001) {
                    continue;
                }

                StockMovement::record($item->product, 'stock_take', $variance, [
                    'reason' => "Stock take {$this->reference}",
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->take_date,
                ]);
            }

            $this->update(['status' => 'posted', 'posted_at' => now()]);
        });
    }
}
