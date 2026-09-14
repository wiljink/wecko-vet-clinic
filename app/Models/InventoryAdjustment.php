<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLocation;
use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InventoryAdjustment extends Model
{
    use BelongsToLocation, GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'ADJ';

    protected $fillable = ['reference', 'adjustment_date', 'location_id', 'remarks', 'status', 'posted_at', 'created_by'];

    protected $casts = ['adjustment_date' => 'date', 'posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->created_by ??= auth()->id());
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function post(): void
    {
        if ($this->isPosted()) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items()->with('product')->get() as $item) {
                StockMovement::record($item->product, 'adjustment', (float) $item->qty_delta, [
                    'location_id' => $this->location_id,
                    'reason' => $item->reason ?: ($this->remarks ?: "Adjustment {$this->reference}"),
                    'expiry_on' => $item->use_by_on,
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->adjustment_date,
                ]);
            }

            $this->update(['status' => 'posted', 'posted_at' => now()]);
        });
    }
}
