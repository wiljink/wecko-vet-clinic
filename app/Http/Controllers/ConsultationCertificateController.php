<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Consultation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConsultationCertificateController extends Controller
{
    public function __invoke(Consultation $consultation): Response
    {
        abort_unless(Auth::user()?->can('view_consultation'), 403);

        $consultation->load(['patient.client', 'patient.species', 'patient.breed', 'provider', 'vaccinations.product']);
        abort_if($consultation->vaccinations->isEmpty(), 404, 'No vaccinations on this consult.');

        $consultation->vaccinations->each->update(['certificate_printed' => true]);

        $pdf = Pdf::loadView('pdf.vaccination-certificate', [
            'consultation' => $consultation,
            'clinic' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->stream("vaccination-certificate-{$consultation->id}.pdf");
    }
}
