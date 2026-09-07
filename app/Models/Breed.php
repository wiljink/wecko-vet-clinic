<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Breed extends Model
{
    use RecordsActivity;

    protected $fillable = ['species_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
