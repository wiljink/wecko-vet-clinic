<?php

namespace App\Support\Import;

use App\Models\Supplier;

class SupplierImporter extends Importer
{
    public function label(): string
    {
        return 'Suppliers';
    }

    public function headers(): array
    {
        return ['name', 'contact_name', 'email', 'phone', 'address', 'account_no', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Zoetis Philippines', 'Maria Santos', 'orders@zoetis.example', '02-8888-1234', '5F Some Tower, Makati', 'ZTS-0091', 'yes'],
            ['MSD Animal Health', '', 'sales@msd.example', '', '', '', 'yes'],
        ];
    }

    public function importRow(array $row, bool $dryRun): string
    {
        $name = $this->str($row, 'name');

        if ($name === '') {
            throw new \RuntimeException('name is required');
        }

        $attributes = [
            'contact_name' => $this->str($row, 'contact_name') ?: null,
            'email' => $this->str($row, 'email') ?: null,
            'phone' => $this->str($row, 'phone') ?: null,
            'address' => $this->str($row, 'address') ?: null,
            'account_no' => $this->str($row, 'account_no') ?: null,
            'is_active' => $this->bool($row, 'is_active', true),
        ];

        $supplier = Supplier::firstOrNew(['name' => $name]);
        $exists = $supplier->exists;
        $supplier->fill($attributes)->save();

        return $exists ? 'updated' : 'created';
    }

    private function bool(array $row, string $key, bool $default): bool
    {
        $v = strtolower($this->str($row, $key));

        return $v === '' ? $default : in_array($v, ['1', 'yes', 'y', 'true', 'active'], true);
    }
}
