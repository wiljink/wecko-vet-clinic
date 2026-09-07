<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'title', 'task_type', 'provider_id', 'task_status_id', 'due_on', 'notes', 'created_by',
    ];

    protected $casts = ['due_on' => 'date'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->created_by ??= auth()->id());
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }
}
