<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\StockTake;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StockTakeSheetController extends Controller
{
    public function __invoke(StockTake $stockTake): Response
    {
        abort_unless(Auth::user()?->can('view_stock_take'), 403);

        $lines = $stockTake->items()->exists()
            ? $stockTake->items()->with('product.group')->get()->map(fn ($i) => $i->product)
            : Product::whereIn('kind', ['product', 'vaccine'])->where('is_active', true)->with('group')->orderBy('name')->get();

        $pdf = Pdf::loadView('pdf.stock-take-sheet', [
            'stockTake' => $stockTake,
            'products' => $lines->sortBy([['group.name', 'asc'], ['name', 'asc']]),
            'clinic' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->stream("stock-take-{$stockTake->reference}.pdf");
    }
}
