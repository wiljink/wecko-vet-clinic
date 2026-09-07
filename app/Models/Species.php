<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Species extends Model
{
    use RecordsActivity;

    protected $table = 'species';

    protected $fillable = ['name', 'size', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
