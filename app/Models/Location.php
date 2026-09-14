<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use RecordsActivity;

    public const TYPE_BRANCH = 'branch';

    public const TYPE_ROOM = 'room';

    protected $fillable = [
        'name', 'type', 'code', 'description', 'address', 'phone', 'email', 'is_main', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_main' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(function (self $location) {
            if ($location->is_main) {
                static::where('type', self::TYPE_BRANCH)->where('id', '!=', $location->id)->update(['is_main' => false]);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBranches(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BRANCH);
    }

    public function scopeRooms(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ROOM);
    }

    public static function main(): ?self
    {
        return static::branches()->where('is_main', true)->first() ?? static::branches()->orderBy('id')->first();
    }
}
