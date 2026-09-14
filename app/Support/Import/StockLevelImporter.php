<?php

namespace App\Support\Import;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStockLevel;
use App\Models\StockMovement;
use App\Support\LocationContext;

/**
 * Sets on-hand quantities from a stock-count / opening-balance sheet, for the
 * current user's acting branch (falling back to the main branch). For each
 * product it posts a single adjusting {@see StockMovement} equal to the
 * difference between the counted quantity and the current cached quantity, so
 * the ledger stays the source of truth.
 */
class StockLevelImporter extends Importer
{
    private ?int $locationId = null;

    private function locationId(): int
    {
        return $this->locationId ??= LocationContext::activeId() ?? Location::main()?->id
            ?? throw new \RuntimeException('No location available to import stock levels into.');
    }

    public function label(): string
    {
        return 'Opening stock / stock levels';
    }

    public function headers(): array
    {
        return ['code', 'barcode', 'name', 'qty_on_hand', 'unit_cost_ex_tax', 'batch_no', 'expiry_on'];
    }

    public function sampleRows(): array
    {
        return [
            ['AMOX250', '', 'Amoxicillin 250mg tablet', '240', '4.50', 'LOT-2291', '2027-03-31'],
            ['', '29000000002', 'Rabies vaccine', '18', '120', '', ''],
        ];
    }

    public function importRow(array $row, bool $dryRun): string
    {
        $code = $this->str($row, 'code');
        $barcode = $this->str($row, 'barcode');
        $name = $this->str($row, 'name');

        $product = null;
        if ($code !== '') {
            $product = Product::where('code', $code)->first();
        }
        if (! $product && $barcode !== '') {
            $product = Product::where('barcode', $barcode)->first();
        }
        if (! $product && $name !== '') {
            $product = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        }

        if (! $product) {
            throw new \RuntimeException('no matching product (by code, barcode or name)');
        }

        if (! $product->tracksStock()) {
            throw new \RuntimeException("{$product->name} is a {$product->kind} and does not carry stock");
        }

        if (($row['qty_on_hand'] ?? '') === '') {
            throw new \RuntimeException('qty_on_hand is required');
        }

        $counted = $this->num($row, 'qty_on_hand');
        $current = ProductStockLevel::qtyOf($product, $this->locationId());
        $delta = round($counted - $current, 2);

        if ($delta === 0.0) {
            return 'skipped';
        }

        if (! $dryRun) {
            StockMovement::record($product, 'stock_take', $delta, array_filter([
                'location_id' => $this->locationId(),
                'reason' => 'Imported stock level',
                'unit_cost_ex_tax' => $this->str($row, 'unit_cost_ex_tax') !== '' ? $this->num($row, 'unit_cost_ex_tax') : $product->unit_cost_ex_tax,
                'batch_no' => $this->str($row, 'batch_no') ?: null,
                'expiry_on' => $this->str($row, 'expiry_on') ?: null,
            ], fn ($v) => $v !== null));
        }

        return 'updated';
    }
}
