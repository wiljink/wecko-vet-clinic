<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use RecordsActivity;

    /** Groups that carry system meaning and cannot be removed. */
    public const PROTECTED_NAMES = ['Desexing', 'Euthanasia', 'Microchipping', 'Vaccinations'];

    protected $fillable = ['name', 'applies_to', 'is_protected', 'is_active'];

    protected $casts = ['is_protected' => 'boolean', 'is_active' => 'boolean'];
}
