<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'TRF';

    protected $fillable = [
        'reference', 'from_location_id', 'to_location_id', 'transfer_date',
        'status', 'notes', 'posted_at', 'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->created_by ??= auth()->id());

        // Visible if either side of the transfer is a branch the user can access.
        static::addGlobalScope('location', function (Builder $query) {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            $ids = $user->accessibleLocationIds();
            $query->where(fn (Builder $q) => $q->whereIn('from_location_id', $ids)->orWhereIn('to_location_id', $ids));
        });
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Post the transfer: a `transfer_out` movement removes stock at the
     * origin branch, a `transfer_in` movement adds it at the destination.
     */
    public function post(): void
    {
        if ($this->isPosted()) {
            return;
        }

        abort_if($this->from_location_id === $this->to_location_id, 422, 'Origin and destination branch must be different.');

        DB::transaction(function () {
            foreach ($this->items()->with('product')->get() as $item) {
                StockMovement::record($item->product, 'transfer_out', -(float) $item->qty, [
                    'location_id' => $this->from_location_id,
                    'reason' => "Transfer {$this->reference} to {$this->toLocation->name}",
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->transfer_date,
                ]);

                StockMovement::record($item->product, 'transfer_in', (float) $item->qty, [
                    'location_id' => $this->to_location_id,
                    'reason' => "Transfer {$this->reference} from {$this->fromLocation->name}",
                    'source_type' => $this->getMorphClass(),
                    'source_id' => $this->id,
                    'moved_at' => $this->transfer_date,
                ]);
            }

            $this->update(['status' => 'posted', 'posted_at' => now()]);
        });
    }
}
