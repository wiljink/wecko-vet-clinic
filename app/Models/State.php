<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function suburbPostcodes(): HasMany
    {
        return $this->hasMany(SuburbPostcode::class);
    }
}
