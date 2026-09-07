<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PatientHistoryController extends Controller
{
    public function __invoke(Patient $patient): Response
    {
        abort_unless(Auth::user()?->can('view_patient'), 403);

        $patient->load(['client', 'species', 'breed', 'colour']);

        foreach (['consultations' => 'consult_date', 'vaccinations' => 'given_on', 'prescriptions' => 'start_on'] as $rel => $col) {
            if (\Illuminate\Support\Facades\Schema::hasTable($rel)) {
                $patient->load([$rel => fn ($q) => $q->latest($col)]);
            } else {
                $patient->setRelation($rel, collect());
            }
        }

        $pdf = Pdf::loadView('pdf.patient-history', [
            'patient' => $patient,
            'clinic' => CompanySetting::current(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->stream("patient-history-{$patient->id}.pdf");
    }
}
