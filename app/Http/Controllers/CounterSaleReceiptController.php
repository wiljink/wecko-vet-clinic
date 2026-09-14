<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\CounterSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 80 mm thermal-style sales receipt for a completed counter sale / POS sale.
 */
class CounterSaleReceiptController extends Controller
{
    public function __invoke(CounterSale $counterSale): Response
    {
        abort_unless(Auth::user()?->can('view_any_counter_sale'), 403);

        $counterSale->load('items', 'client', 'provider', 'invoice.allocations.payment.cardType');

        // 80 mm wide, height grows with content.
        $lines = 26 + $counterSale->items->count() * 4;
        $pdf = Pdf::loadView('pdf.counter-sale-receipt', [
            'sale' => $counterSale,
            'clinic' => CompanySetting::current(),
        ])->setPaper([0, 0, 226.77, max(360, $lines * 12)]);

        return $pdf->stream("receipt-{$counterSale->sale_no}.pdf");
    }
}
