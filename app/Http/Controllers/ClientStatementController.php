<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Support\ClientLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientStatementController extends Controller
{
    public function __invoke(Request $request, Client $client): Response
    {
        abort_unless(Auth::user()?->can('view_any_invoice'), 403);

        $from = $request->date('from') ?? now()->subMonths(6)->startOfMonth();
        $to = $request->date('to') ?? now();

        $pdf = Pdf::loadView('pdf.client-statement', [
            'client' => $client,
            'clinic' => CompanySetting::current(),
            'statement' => ClientLedger::statement($client, Carbon::parse($from), Carbon::parse($to)),
            'aging' => ClientLedger::aging($client),
            'from' => Carbon::parse($from),
            'to' => Carbon::parse($to),
        ])->setPaper('a4');

        return $pdf->stream("statement-{$client->id}.pdf");
    }
}
