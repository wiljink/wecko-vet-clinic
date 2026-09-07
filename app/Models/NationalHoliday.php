<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'holiday_date', 'description', 'is_active'];

    protected $casts = ['holiday_date' => 'date', 'is_active' => 'boolean'];
}
