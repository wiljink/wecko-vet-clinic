<?php

namespace App\Models\Concerns;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Adds the `location` relation and restricts every query to the current
 * user's accessible branches (all branches for principal, only their home
 * branch otherwise — see User::accessibleLocationIds()). Scoped out entirely
 * when there's no authenticated user (console, seeding, queued jobs).
 */
trait BelongsToLocation
{
    protected static function bootBelongsToLocation(): void
    {
        static::addGlobalScope('location', function (Builder $query) {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            $column = $query->getModel()->getTable().'.location_id';
            $ids = $user->accessibleLocationIds();

            // A record with no branch assigned is visible to everyone — only
            // records assigned to a branch outside the user's access are hidden.
            $query->where(fn (Builder $q) => $q->whereIn($column, $ids)->orWhereNull($column));
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
