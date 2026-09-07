<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class Colour extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
