<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuburbPostcode extends Model
{
    use RecordsActivity;

    protected $fillable = ['state_id', 'suburb', 'postcode', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
