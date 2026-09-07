<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PatientReminderType extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name', 'category', 'period_years', 'period_months', 'period_days', 'is_active',
    ];

    protected $casts = [
        'period_years' => 'integer',
        'period_months' => 'integer',
        'period_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Add this reminder type's interval to a base date. */
    public function nextDueFrom(Carbon $base): Carbon
    {
        return $base->copy()
            ->addYears($this->period_years)
            ->addMonths($this->period_months)
            ->addDays($this->period_days);
    }
}
