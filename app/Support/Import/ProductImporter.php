<?php

namespace App\Support\Import;

use App\Models\Group;
use App\Models\Product;
use App\Models\Supplier;

/**
 * Imports the product / vaccine / service catalogue. Rows are matched on `code`
 * when present, otherwise on `barcode`, otherwise on `name` + `kind`.
 */
class ProductImporter extends Importer
{
    public function label(): string
    {
        return 'Products & services';
    }

    public function headers(): array
    {
        return [
            'name', 'kind', 'code', 'barcode', 'group', 'supplier',
            'tax_rate', 'unit_cost_ex_tax', 'sell_price_ex_tax', 'dispense_fee',
            'reorder_level', 'max_holding', 'list_this_product', 'is_active',
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['Amoxicillin 250mg tablet', 'product', 'AMOX250', '29000000001', 'Drugs', 'Zoetis Philippines', '12', '4.50', '12.00', '20.00', '50', '300', 'both', 'yes'],
            ['Rabies vaccine', 'vaccine', 'RABIES', '29000000002', 'Vaccinations', 'MSD Animal Health', '12', '120', '350', '0', '10', '60', 'consult', 'yes'],
            ['Consultation - standard', 'service', 'CONSULT', '', 'Consultation', '', '12', '0', '500', '0', '', '', 'both', 'yes'],
        ];
    }

    public function importRow(array $row, bool $dryRun): string
    {
        $name = $this->str($row, 'name');

        if ($name === '') {
            throw new \RuntimeException('name is required');
        }

        $kind = strtolower($this->str($row, 'kind')) ?: 'product';

        if (! array_key_exists($kind, Product::KINDS)) {
            throw new \RuntimeException("unknown kind “{$kind}” (use product, vaccine or service)");
        }

        $code = $this->str($row, 'code') ?: null;
        $barcode = $this->str($row, 'barcode') ?: null;

        $product = null;
        if ($code) {
            $product = Product::where('code', $code)->first();
        }
        if (! $product && $barcode) {
            $product = Product::where('barcode', $barcode)->first();
        }
        if (! $product) {
            $product = Product::where('kind', $kind)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        }

        $exists = (bool) $product;
        $product ??= new Product(['kind' => $kind]);

        $attributes = array_filter([
            'name' => $name,
            'kind' => $kind,
            'code' => $code,
            'barcode' => $barcode,
            'list_this_product' => $this->listing($row),
        ], fn ($v) => $v !== null);

        $attributes['tax_rate'] = $this->num($row, 'tax_rate', (float) ($product->tax_rate ?? 12));
        $attributes['sell_price_ex_tax'] = $this->num($row, 'sell_price_ex_tax', (float) ($product->sell_price_ex_tax ?? 0));
        $attributes['unit_cost_ex_tax'] = $this->num($row, 'unit_cost_ex_tax', (float) ($product->unit_cost_ex_tax ?? 0));
        $attributes['dispense_fee'] = $this->num($row, 'dispense_fee', (float) ($product->dispense_fee ?? 0));
        $attributes['reorder_level'] = $this->num($row, 'reorder_level', (float) ($product->reorder_level ?? 0));
        $attributes['max_holding'] = $this->num($row, 'max_holding', (float) ($product->max_holding ?? 0));

        if (($group = $this->str($row, 'group')) !== '') {
            $attributes['group_id'] = Group::firstOrCreate(['name' => $group], ['applies_to' => 'both'])->id;
        }
        if (($supplier = $this->str($row, 'supplier')) !== '') {
            $attributes['supplier_id'] = Supplier::firstOrCreate(['name' => $supplier])->id;
        }
        if (($active = strtolower($this->str($row, 'is_active'))) !== '') {
            $attributes['is_active'] = in_array($active, ['1', 'yes', 'y', 'true', 'active'], true);
        }

        $product->fill($attributes)->save();

        return $exists ? 'updated' : 'created';
    }

    private function listing(array $row): ?string
    {
        $v = strtolower($this->str($row, 'list_this_product'));

        return in_array($v, ['none', 'consult', 'otc', 'both'], true) ? $v : null;
    }
}
