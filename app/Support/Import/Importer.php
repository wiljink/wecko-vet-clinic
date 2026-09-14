<?php

namespace App\Support\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base class for CSV importers used by the Setup > Data Import page.
 *
 * Each importer declares its expected column headers, provides a downloadable
 * template, and knows how to upsert one parsed row. Header matching is
 * case-insensitive and ignores spaces / underscores.
 */
abstract class Importer
{
    /** @return array<int, string> canonical column headers, first one is the match key label */
    abstract public function headers(): array;

    /** @return array<int, array<int, string>> example rows for the template */
    abstract public function sampleRows(): array;

    /** Human label for the picker. */
    abstract public function label(): string;

    /**
     * Apply one row. Return 'created' | 'updated' | 'skipped'. Throw to record an error.
     *
     * @param  array<string, string>  $row  keyed by canonical header
     */
    abstract public function importRow(array $row, bool $dryRun): string;

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    public function run(array $rows, bool $dryRun): ImportResult
    {
        $result = new ImportResult;
        $result->dryRun = $dryRun;

        $runner = function () use ($rows, $dryRun, $result) {
            foreach ($rows as $index => $row) {
                $line = $index + 2; // header is row 1

                try {
                    match ($this->importRow($row, $dryRun)) {
                        'created' => $result->created++,
                        'updated' => $result->updated++,
                        default => $result->skipped++,
                    };
                } catch (\Throwable $e) {
                    $result->error($line, $e->getMessage());
                }
            }

            if ($dryRun) {
                throw new DryRunComplete;
            }
        };

        try {
            DB::transaction($runner);
        } catch (DryRunComplete) {
            // expected — rolls back every change made during the preview
        }

        return $result;
    }

    /** Normalise raw CSV headers to canonical ones ("Unit Price" -> "unit_price"). */
    public function mapHeaders(array $rawHeaders): array
    {
        $canon = collect($this->headers())
            ->mapWithKeys(fn ($h) => [Str::of($h)->lower()->replace([' ', '_', '-'], '')->value() => $h])
            ->all();

        return array_map(
            fn ($h) => $canon[Str::of((string) $h)->lower()->replace([' ', '_', '-'], '')->value()] ?? trim((string) $h),
            $rawHeaders,
        );
    }

    protected function num(array $row, string $key, float $default = 0): float
    {
        $v = $row[$key] ?? '';

        return $v === '' ? $default : (float) str_replace([',', '₱', '$'], '', $v);
    }

    protected function str(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }
}
