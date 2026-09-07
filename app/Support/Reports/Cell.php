<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;

/**
 * Formats a single report cell for on-screen / PDF display and for CSV export.
 * Types: text, money, number, percent, date.
 */
class Cell
{
    /** Swapped to "PHP " while rendering PDFs (DejaVu has no ₱ glyph). */
    public static string $currencySymbol = '₱';

    public static function display(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return $type === 'text' ? '' : '—';
        }

        return match ($type) {
            'money' => ((float) $value < 0 ? '-' : '').self::$currencySymbol.number_format(abs((float) $value), 2),
            'number' => is_numeric($value) ? number_format((float) $value) : (string) $value,
            'percent' => number_format((float) $value, 1).'%',
            'date' => self::asDate($value),
            default => (string) $value,
        };
    }

    private static function asDate(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function raw(string $type, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($type, ['money', 'percent', 'number'], true) && ! is_numeric($value)) {
            return (string) $value;
        }

        return match ($type) {
            'money', 'percent' => number_format((float) $value, 2, '.', ''),
            'number' => (string) (int) round((float) $value),
            'date' => rescue(fn () => Carbon::parse($value)->toDateString(), (string) $value, false),
            default => (string) $value,
        };
    }

    /** @param array{type?: string} $column */
    public static function alignRight(array $column): bool
    {
        return in_array($column['type'] ?? 'text', ['money', 'number', 'percent'], true);
    }
}
