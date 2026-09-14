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
        static::saving(function (self $location) {
            // The only main branch can't be turned off directly — main only ever
            // changes by switching a *different* branch to main (handled below).
            // Also covers the very first branch ever created, which has no
            // other main to fall back to.
            if ($location->type !== self::TYPE_BRANCH || $location->is_main) {
                return;
            }

            $hasOtherMain = static::branches()->where('is_main', true)
                ->when($location->exists, fn ($q) => $q->where('id', '!=', $location->id))
                ->exists();

            if (! $hasOtherMain) {
                $location->is_main = true;
            }
        });

        static::saved(function (self $location) {
            if ($location->is_main) {
                static::where('type', self::TYPE_BRANCH)->where('id', '!=', $location->id)->update(['is_main' => false]);
            }
        });

        static::deleting(function (self $location) {
            abort_unless($location->isDeletable(), 422, $location->is_main
                ? 'The main branch cannot be deleted — make another branch main first.'
                : 'The last remaining branch cannot be deleted — the system must always have at least one.');
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

    /** A branch is undeletable while it's main, or the only branch left; rooms are always deletable. */
    public function isDeletable(): bool
    {
        if ($this->type !== self::TYPE_BRANCH) {
            return true;
        }

        return ! $this->is_main && static::branches()->count() > 1;
    }
}
