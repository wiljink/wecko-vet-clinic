<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class AppointmentReason extends Model
{
    use RecordsActivity;

    protected $fillable = ['code', 'reason', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
