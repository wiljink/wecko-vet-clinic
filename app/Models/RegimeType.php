<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class RegimeType extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'total_qty_used', 'is_active'];

    protected $casts = ['total_qty_used' => 'decimal:2', 'is_active' => 'boolean'];
}
