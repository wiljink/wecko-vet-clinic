<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveVacationBreak extends Model
{
    use RecordsActivity;

    protected $fillable = ['user_id', 'type', 'starts_at', 'ends_at', 'notes'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
