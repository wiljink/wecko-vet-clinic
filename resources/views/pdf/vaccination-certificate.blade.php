<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #1e293b; margin: 40px; }
    .frame { border: 3px double #0d9488; padding: 30px 36px; }
    h1 { text-align: center; color: #0f766e; margin: 0 0 4px; letter-spacing: .06em; }
    .clinic { text-align: center; color: #475569; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; margin: 14px 0; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
    th { background: #f0fdfa; }
    .muted { color: #64748b; }
    .sign { margin-top: 48px; }
</style>
</head>
<body>
<div class="frame">
    <h1>Vaccination Certificate</h1>
    <div class="clinic">
        {{ $clinic->company_name }} — {!! nl2br(e($clinic->address)) !!}<br>
        {{ $clinic->phone }} · {{ $clinic->email }}
    </div>

    <p>This is to certify that the animal described below has been vaccinated as recorded.</p>

    <table>
        <tr><th style="width:35%;">Patient</th><td>{{ $consultation->patient->name }}</td></tr>
        <tr><th>Owner</th><td>{{ $consultation->patient->client?->full_name }}</td></tr>
        <tr><th>Species / Breed</th><td>{{ $consultation->patient->species?->name }} / {{ $consultation->patient->breed?->name ?: '—' }}</td></tr>
        <tr><th>Sex</th><td>{{ ucfirst($consultation->patient->gender) }}</td></tr>
        <tr><th>Microchip #</th><td>{{ $consultation->patient->microchip_no ?: '—' }}</td></tr>
        <tr><th>Date of birth</th><td>{{ optional($consultation->patient->birth_date)->format('d M Y') ?: '—' }}</td></tr>
    </table>

    <table>
        <thead><tr><th>Vaccine</th><th>Batch</th><th>Date given</th><th>Booster due</th></tr></thead>
        <tbody>
        @foreach($consultation->vaccinations as $v)
            <tr>
                <td>{{ $v->name }}<br><span class="muted">{{ $v->protection }}</span></td>
                <td>{{ $v->batch_no ?: '—' }}</td>
                <td>{{ optional($v->given_on)->format('d M Y') }}</td>
                <td>{{ optional($v->booster_due_on)->format('d M Y') ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="sign">
        <p>_______________________________<br>
        {{ $consultation->provider?->name ?? 'Attending Veterinarian' }}
        @if($consultation->provider?->licence_no)<br><span class="muted">PRC Licence {{ $consultation->provider->licence_no }}</span>@endif
        </p>
        <p class="muted">Issued {{ now()->format('d M Y') }}</p>
    </div>
</div>
</body>
</html>
