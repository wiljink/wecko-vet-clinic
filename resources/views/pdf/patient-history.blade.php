<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #1e293b; margin: 24px; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    h2 { font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 18px 0 6px; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
    .grid td { border: none; padding: 2px 6px; }
</style>
</head>
<body>
    <div style="float:right; text-align:right;" class="muted">
        {{ $clinic->company_name }}<br>
        {!! nl2br(e($clinic->address)) !!}<br>
        {{ $clinic->phone }} · {{ $clinic->email }}
    </div>
    <h1>Patient Medical History</h1>
    <div class="muted">Generated {{ $generatedAt->format('d M Y H:i') }}</div>

    <h2>Patient</h2>
    <table class="grid">
        <tr><td class="muted">Name</td><td>{{ $patient->name }}</td>
            <td class="muted">Owner</td><td>{{ $patient->client?->full_name }}</td></tr>
        <tr><td class="muted">Species / Breed</td><td>{{ $patient->species?->name }} / {{ $patient->breed?->name ?: '—' }}</td>
            <td class="muted">Colour</td><td>{{ $patient->colour?->name ?: '—' }}</td></tr>
        <tr><td class="muted">Sex</td><td>{{ ucfirst($patient->gender) }} ({{ str_replace('_',' ',$patient->neuter_status) }})</td>
            <td class="muted">Age</td><td>{{ $patient->age_label }}</td></tr>
        <tr><td class="muted">Microchip</td><td>{{ $patient->microchip_no ?: '—' }}</td>
            <td class="muted">Weight</td><td>{{ $patient->weight ? $patient->weight.' kg' : '—' }}</td></tr>
        @if($patient->behavioural_warning)
        <tr><td class="muted">Warning</td><td colspan="3"><strong>{{ $patient->behavioural_warning }}</strong></td></tr>
        @endif
    </table>

    <h2>Consultations ({{ $patient->consultations->count() }})</h2>
    @forelse($patient->consultations as $c)
        <p style="margin:10px 0 2px;"><strong>{{ optional($c->consult_date)->format('d M Y') }}</strong>
            — {{ $c->provider?->name }} · {{ ucfirst($c->status) }}</p>
        <table class="grid">
            @if($c->history)<tr><td class="muted" style="width:110px;">History</td><td>{{ $c->history }}</td></tr>@endif
            @if($c->examination)<tr><td class="muted">Examination</td><td>{{ $c->examination }}</td></tr>@endif
            @if($c->consult_diagnosis)<tr><td class="muted">Diagnosis</td><td>{{ $c->consult_diagnosis }}</td></tr>@endif
            @if($c->treatment)<tr><td class="muted">Treatment</td><td>{{ $c->treatment }}</td></tr>@endif
        </table>
    @empty
        <p class="muted">No consultations recorded.</p>
    @endforelse

    <h2>Vaccinations</h2>
    <table>
        <thead><tr><th>Date</th><th>Vaccine</th><th>Batch</th><th>Booster due</th></tr></thead>
        <tbody>
        @forelse($patient->vaccinations as $v)
            <tr><td>{{ optional($v->given_on)->format('d M Y') }}</td><td>{{ $v->name }}</td>
                <td>{{ $v->batch_no ?: '—' }}</td><td>{{ optional($v->booster_due_on)->format('d M Y') ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">None recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Prescriptions</h2>
    <table>
        <thead><tr><th>Date</th><th>Drug</th><th>Regime</th><th>Qty</th></tr></thead>
        <tbody>
        @forelse($patient->prescriptions as $p)
            <tr><td>{{ optional($p->start_on)->format('d M Y') }}</td><td>{{ $p->drug_name }}</td>
                <td>{{ $p->dosage ?: '—' }}</td><td>{{ $p->quantity }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">None recorded.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
