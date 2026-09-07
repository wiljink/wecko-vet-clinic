<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAdjustment extends Model
{
    use GeneratesReference, RecordsActivity;

    protected string $referencePrefix = 'ACJ';

    protected $fillable = [
        'reference', 'client_id', 'direction', 'reason', 'amount', 'invoice_id', 'adjusted_on', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'adjusted_on' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            $a->created_by ??= auth()->id();
            $a->adjusted_on ??= now()->toDateString();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
