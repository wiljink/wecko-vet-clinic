<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        abort_unless(Auth::user()?->can('view_any_payment'), 403);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment->load('client', 'allocations.invoice', 'cardType'),
            'clinic' => CompanySetting::current(),
        ])->setPaper('a5', 'landscape');

        return $pdf->stream("receipt-{$payment->payment_no}.pdf");
    }
}
