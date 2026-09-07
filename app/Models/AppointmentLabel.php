<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AppointmentLabel extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'menu_caption', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
