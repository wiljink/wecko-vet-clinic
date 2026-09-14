<?php

namespace App\Support;

/**
 * Fixed-order categorical palette for the dashboard's stats and charts.
 * Order matters — it's what keeps adjacent colors distinguishable for
 * colorblind viewers, so slots are assigned in this sequence, never cycled
 * or picked ad hoc. Hex values are the dark-surface steps (this panel
 * defaults to dark mode) — they clear contrast on both surfaces since
 * they're the more saturated of the two validated sets.
 */
class ChartPalette
{
    public const BLUE = '#3987e5';

    public const ORANGE = '#d95926';

    public const AQUA = '#199e70';

    public const YELLOW = '#c98500';

    public const MAGENTA = '#d55181';

    public const GREEN = '#008300';

    public const VIOLET = '#9085e9';

    public const RED = '#e66767';

    public const GRAY = '#898781';

    /** The eight categorical hues in their validated fixed order. */
    public const SEQUENCE = [
        self::BLUE, self::ORANGE, self::AQUA, self::YELLOW,
        self::MAGENTA, self::GREEN, self::VIOLET, self::RED,
    ];

    /** @return array{backgroundColor: string, borderColor: string} Chart.js dataset color pair for a hue. */
    public static function dataset(string $hex, float $fillOpacity = 0.65): array
    {
        return [
            'backgroundColor' => static::withAlpha($hex, $fillOpacity),
            'borderColor' => $hex,
        ];
    }

    public static function withAlpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /**
     * Extra HTML attributes for a Stat card's accent border. Filament's stock
     * stats-overview blade doesn't tint the card/value/icon from Stat::color()
     * (it only feeds an optional description or sparkline), so a visible
     * per-stat color needs to land via extraAttributes() instead.
     */
    public static function statAccentAttributes(string $hex): array
    {
        return ['style' => "border-inline-start: 4px solid {$hex};"];
    }
}
