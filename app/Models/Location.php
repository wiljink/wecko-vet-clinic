<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
