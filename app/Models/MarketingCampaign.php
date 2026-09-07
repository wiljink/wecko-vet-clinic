<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name', 'channels', 'document_template_id', 'filter', 'ran_at', 'recipients', 'created_by',
    ];

    protected $casts = [
        'channels' => 'array',
        'filter' => 'array',
        'ran_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->created_by ??= auth()->id());
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
