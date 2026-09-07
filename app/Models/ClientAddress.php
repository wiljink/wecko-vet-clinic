<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
    protected $fillable = [
        'client_id', 'label', 'line1', 'line2', 'suburb', 'postcode',
        'state_id', 'street_directory_ref', 'travel_distance', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function getOneLineAttribute(): string
    {
        return trim(implode(', ', array_filter([
            $this->line1, $this->line2, $this->suburb, $this->postcode, $this->state?->name,
        ])));
    }
}
