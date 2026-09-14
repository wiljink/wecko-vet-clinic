<?php

namespace App\Support;

/**
 * Dependency-free Code 128 barcode renderer (SVG output).
 *
 * Code 128 encodes the full ASCII set and is the de-facto default for retail
 * shelf labels and internal SKUs. The renderer auto-switches between code sets
 * B and C so long digit runs stay compact, and appends the modulo-103 checksum.
 *
 * Usage:
 *   Barcode::svg('WECKO-00042');            // inline <svg> string
 *   Barcode::dataUri('4801234567890');      // data: URI for <img src>
 */
class Barcode
{
    /** 107 bar/space patterns, indexed by Code 128 value (0-106). */
    private const PATTERNS = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '11000111010',
    ];

    private const STOP = '1100011101011';

    private const START_B = 104;

    private const START_C = 105;

    /** Render an SVG string for the given value. Returns null for empty input. */
    public static function svg(string $value, int $height = 60, float $moduleWidth = 1.6, bool $showText = true): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $modules = self::encode($value);
        $width = strlen($modules) * $moduleWidth;
        $quiet = 10 * $moduleWidth;
        $textH = $showText ? 14 : 0;
        $totalW = $width + 2 * $quiet;
        $totalH = $height + $textH;

        $rects = '';
        $x = $quiet;
        foreach (self::runs($modules) as [$isBar, $len]) {
            $w = $len * $moduleWidth;
            if ($isBar) {
                $rects .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d"/>', $x, $w, $height);
            }
            $x += $w;
        }

        $text = $showText
            ? sprintf(
                '<text x="%.2f" y="%d" text-anchor="middle" font-family="monospace" font-size="12">%s</text>',
                $totalW / 2,
                $height + 12,
                htmlspecialchars($value, ENT_QUOTES),
            )
            : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2f" height="%.2f" viewBox="0 0 %.2f %.2f">'
            .'<rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g>%s</svg>',
            $totalW,
            $totalH,
            $totalW,
            $totalH,
            $rects,
            $text,
        );
    }

    /** Base64 data: URI, handy for dompdf <img> tags and Blade. */
    public static function dataUri(string $value, int $height = 60, float $moduleWidth = 1.6, bool $showText = true): ?string
    {
        $svg = self::svg($value, $height, $moduleWidth, $showText);

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    /** Generate a fresh numeric internal barcode (prefix 29 = in-store use). */
    public static function generate(): string
    {
        return '29'.str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
    }

    /** @return string binary module string ("1" = bar unit, "0" = space unit) */
    private static function encode(string $value): string
    {
        $codes = [];
        $len = strlen($value);
        $useC = self::digitsAhead($value, 0) >= ($len >= 4 ? 4 : $len) && $len >= 2 && ctype_digit(substr($value, 0, 2));

        $codes[] = $useC ? self::START_C : self::START_B;
        $mode = $useC ? 'C' : 'B';
        $i = 0;

        while ($i < $len) {
            if ($mode === 'C') {
                if (self::digitsAhead($value, $i) >= 2) {
                    $codes[] = (int) substr($value, $i, 2);
                    $i += 2;

                    continue;
                }
                $codes[] = 100; // Code B
                $mode = 'B';
            }

            // mode B
            if (self::digitsAhead($value, $i) >= 6 || (self::digitsAhead($value, $i) >= 4 && $i + self::digitsAhead($value, $i) === $len)) {
                $codes[] = 99; // Code C
                $mode = 'C';

                continue;
            }

            $codes[] = ord($value[$i]) - 32;
            $i++;
        }

        // modulo-103 checksum
        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $pos => $code) {
            $sum += $code * ($pos + 1);
        }
        $codes[] = $sum % 103;

        $bits = '';
        foreach ($codes as $code) {
            $bits .= self::PATTERNS[$code];
        }

        return $bits.self::STOP;
    }

    private static function digitsAhead(string $value, int $from): int
    {
        $n = 0;
        $len = strlen($value);
        while ($from + $n < $len && ctype_digit($value[$from + $n])) {
            $n++;
        }

        return $n;
    }

    /** @return array<int, array{0: bool, 1: int}> [isBar, runLength] pairs */
    private static function runs(string $modules): array
    {
        $runs = [];
        $current = $modules[0];
        $count = 0;

        for ($i = 0, $n = strlen($modules); $i < $n; $i++) {
            if ($modules[$i] === $current) {
                $count++;

                continue;
            }
            $runs[] = [$current === '1', $count];
            $current = $modules[$i];
            $count = 1;
        }
        $runs[] = [$current === '1', $count];

        return $runs;
    }
}
