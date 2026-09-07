<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AccountNameReference extends Model
{
    use RecordsActivity;

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
