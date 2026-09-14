<?php

namespace App\Support\Import;

/**
 * Outcome of a (dry-run or committed) import: per-outcome counts plus the
 * first handful of row-level errors for display.
 */
class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public bool $dryRun = true;

    public function error(int $row, string $message): void
    {
        $this->skipped++;

        if (count($this->errors) < 25) {
            $this->errors[] = "Row {$row}: {$message}";
        }
    }

    public function processed(): int
    {
        return $this->created + $this->updated;
    }

    public function summary(): string
    {
        $verb = $this->dryRun ? 'would be' : 'were';

        return "{$this->created} created, {$this->updated} updated, {$this->skipped} skipped ({$verb} applied).";
    }
}
