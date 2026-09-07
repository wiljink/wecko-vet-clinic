<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'job_position_id',
        'licence_no',
        'is_provider',
        'is_principal',
        'can_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_provider' => 'boolean',
            'is_principal' => 'boolean',
            'can_login' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can_login;
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    /** A provider is a vet/nurse who attends patients and owns appointments. */
    public function scopeProviders($query)
    {
        return $query->where('is_provider', true);
    }
}
