<?php

namespace App\Models\Concerns;

/**
 * Auto-assigns a human document number on create, e.g. "SR-2026-0007".
 * The using model sets:  protected string $referenceColumn = 'receipt_no';
 *                        protected string $referencePrefix = 'SR';
 */
trait GeneratesReference
{
    public static function bootGeneratesReference(): void
    {
        static::creating(function ($model) {
            $column = $model->referenceColumn ?? 'reference';

            if (! empty($model->{$column})) {
                return;
            }

            $prefix = $model->referencePrefix ?? strtoupper(substr(class_basename($model), 0, 3));
            $year = now()->year;

            $last = static::query()
                ->where($column, 'like', "{$prefix}-{$year}-%")
                ->orderByDesc($column)
                ->value($column);

            $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

            $model->{$column} = sprintf('%s-%d-%04d', $prefix, $year, $seq);
        });
    }
}
