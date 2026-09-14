<?php

namespace App\Support;

use App\Models\Location;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Resolves "what branch is the current user acting as" for the request:
 * the default for new-record location_id fields, and the dashboard's
 * branch filter. Principals may switch branch (or view "All Branches",
 * represented as null); everyone else is pinned to their home branch.
 */
class LocationContext
{
    private const SESSION_KEY = 'active_location_id';

    public static function activeId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if (! $user->canSwitchLocation()) {
            return $user->home_location_id;
        }

        $sessionId = Session::get(self::SESSION_KEY);
        $accessible = $user->accessibleLocationIds();

        if ($sessionId && in_array($sessionId, $accessible, true)) {
            return $sessionId;
        }

        // A principal with no personal home branch still needs a default to
        // act as (e.g. Point of Sale, new records) — fall back to the main branch.
        return $user->home_location_id ?? Location::main()?->id;
    }

    public static function setActive(?int $locationId): void
    {
        $user = Auth::user();

        if (! $user || ! $user->canSwitchLocation()) {
            return;
        }

        if ($locationId !== null && ! in_array($locationId, $user->accessibleLocationIds(), true)) {
            return;
        }

        Session::put(self::SESSION_KEY, $locationId);
    }

    public static function canSwitch(): bool
    {
        return (bool) Auth::user()?->canSwitchLocation();
    }

    /**
     * The branch id a branch-scoped view (like the dashboard) should use:
     * whatever the user picked in the filter if they're allowed to switch,
     * otherwise always their own home branch regardless of the filter state.
     */
    public static function effectiveFilterId(?array $filters): ?int
    {
        if (! static::canSwitch()) {
            return static::activeId();
        }

        return $filters['location_id'] ?? null;
    }

    /** @return array<int, string> id => name, scoped to what the user may access. */
    public static function accessibleOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return Location::query()
            ->whereIn('id', $user->accessibleLocationIds())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * A `location_id` Select pre-wired for every resource form: defaults to
     * the acting branch, locked (but still submitted) unless the user can
     * switch branches. `$name` lets callers target `from_location_id` etc.
     */
    public static function selectField(string $name = 'location_id', string $label = 'Branch'): Select
    {
        return Select::make($name)->label($label)
            ->relationship('location', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->branches())
            ->default(fn () => static::activeId())
            ->disabled(fn () => ! static::canSwitch())
            ->dehydrated()
            ->required();
    }
}
