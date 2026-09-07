<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AppointmentStatus extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'menu_caption', 'color', 'is_default', 'is_active'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];
}
