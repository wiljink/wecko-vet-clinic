<?php

namespace App\Support;

/**
 * Shared money maths for invoice / consultation / counter-sale line items.
 * All inputs and outputs are tax-exclusive unless the key says "inc_tax".
 */
class LineTotals
{
    /**
     * @return array{ex_tax: float, tax: float, inc_tax: float, discount: float}
     */
    public static function forLine(
        float $qty,
        float $unitPriceExTax,
        float $taxRate,
        float $discountPct = 0,
        float $dispensingFee = 0,
        float $injectionFee = 0,
    ): array {
        $gross = round($qty * $unitPriceExTax, 2);
        $discount = round($gross * $discountPct / 100, 2);
        $netEx = round($gross - $discount + $dispensingFee + $injectionFee, 2);
        $tax = round($netEx * $taxRate / 100, 2);

        return [
            'ex_tax' => $netEx,
            'tax' => $tax,
            'inc_tax' => round($netEx + $tax, 2),
            'discount' => $discount,
        ];
    }
}
