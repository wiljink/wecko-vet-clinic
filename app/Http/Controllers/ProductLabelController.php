<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Support\Barcode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable barcode shelf / bin labels for one or more products.
 * ?ids=1,2,3  &per=<repeat count per product, default 1>
 */
class ProductLabelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Auth::user()?->can('view_any_product'), 403);

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))->filter()->all();

        abort_if($ids === [], 404, 'No products selected.');

        $per = max(1, min(40, (int) $request->query('per', 1)));

        $labels = Product::whereIn('id', $ids)->get()
            ->filter(fn (Product $p) => (bool) $p->barcode)
            ->flatMap(fn (Product $p) => array_fill(0, $per, [
                'name' => $p->name,
                'code' => $p->code,
                'price' => number_format((float) $p->sell_price_inc_tax, 2),
                'barcode' => $p->barcode,
                'svg' => Barcode::dataUri($p->barcode, 44, 1.3),
            ]))
            ->values();

        abort_if($labels->isEmpty(), 404, 'None of the selected products have a barcode.');

        $pdf = Pdf::loadView('pdf.product-labels', [
            'labels' => $labels,
            'clinic' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->stream('barcode-labels.pdf');
    }
}
