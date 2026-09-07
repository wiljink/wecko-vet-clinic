<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #1e293b; margin: 28px; }
    h1 { font-size: 17px; margin: 0; }
    .muted { color: #64748b; }
    .right { text-align: right; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    th { background: #f1f5f9; text-transform: uppercase; font-size: 9px; }
    .totrow td { border-top: 2px solid #94a3b8; font-weight: bold; }
    .aging td, .aging th { border: 1px solid #cbd5e1; text-align: center; }
</style></head><body>
    <div style="float:right; text-align:right;" class="muted">
        {{ $clinic->company_name }}<br>{!! nl2br(e($clinic->address)) !!}<br>
        {{ $clinic->phone }} · TIN {{ $clinic->tin }}
    </div>
    <h1>Statement of Account</h1>
    <div class="muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · Issued {{ now()->format('d M Y') }}</div>

    <p style="margin-top:14px;">
        <strong>{{ $client->full_name }}</strong><br>
        <span class="muted">{{ $client->primaryAddress()?->one_line }}</span>
    </p>

    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Particulars</th>
            <th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead>
        <tbody>
            <tr class="muted"><td colspan="6">Opening balance</td><td class="right">₱{{ number_format($statement['opening'], 2) }}</td></tr>
            @foreach ($statement['rows'] as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td>{{ $row['type'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['particulars'] }}</td>
                    <td class="right">{{ $row['debit'] ? '₱'.number_format($row['debit'], 2) : '' }}</td>
                    <td class="right">{{ $row['credit'] ? '₱'.number_format($row['credit'], 2) : '' }}</td>
                    <td class="right">₱{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="totrow"><td colspan="6">Balance due</td><td class="right">₱{{ number_format($statement['closing'], 2) }}</td></tr>
        </tbody>
    </table>

    <table class="aging" style="width:60%; margin-top:20px;">
        <thead><tr><th>Current</th><th>1 period</th><th>2 periods</th><th>3+ periods</th><th>Total</th></tr></thead>
        <tbody><tr>
            <td>₱{{ number_format($aging['current'], 2) }}</td>
            <td>₱{{ number_format($aging['b1'], 2) }}</td>
            <td>₱{{ number_format($aging['b2'], 2) }}</td>
            <td>₱{{ number_format($aging['b3'], 2) }}</td>
            <td>₱{{ number_format($aging['total'], 2) }}</td>
        </tr></tbody>
    </table>

    <p class="muted" style="margin-top:18px;">Please settle any outstanding balance within {{ $clinic->accounting_period_days }} days. Thank you for choosing {{ $clinic->company_name }}.</p>
</body></html>
