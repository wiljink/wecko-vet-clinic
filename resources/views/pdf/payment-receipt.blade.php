<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #1e293b; margin: 24px; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td, th { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
</style></head><body>
    <div style="float:right;text-align:right;" class="muted">{{ $clinic->company_name }}<br>{{ $clinic->phone }}</div>
    <h1>{{ $payment->is_refund ? 'Refund' : 'Official Receipt' }}</h1>
    <div class="muted">{{ $payment->payment_no }} · {{ $payment->received_at->format('d M Y H:i') }}</div>

    <table>
        <tr><td class="muted" style="width:35%;">Received from</td><td>{{ $payment->client?->full_name }}</td></tr>
        <tr><td class="muted">Method</td><td>{{ \App\Models\Payment::TYPES[$payment->payment_type] ?? $payment->payment_type }}
            {{ $payment->cardType ? '('.$payment->cardType->name.')' : '' }}</td></tr>
        <tr><td class="muted">Amount</td><td><strong>₱{{ number_format((float) $payment->amount, 2) }}</strong></td></tr>
        @if ($payment->cash_received)
            <tr><td class="muted">Cash received</td><td>₱{{ number_format((float) $payment->cash_received, 2) }}</td></tr>
            <tr><td class="muted">Change</td><td>₱{{ number_format((float) $payment->change_given, 2) }}</td></tr>
        @endif
        @if ($payment->reference)<tr><td class="muted">Reference</td><td>{{ $payment->reference }}</td></tr>@endif
    </table>

    @if ($payment->allocations->isNotEmpty())
        <p class="muted" style="margin-top:10px;">Applied to:</p>
        <table>
            @foreach ($payment->allocations as $alloc)
                <tr><td>{{ $alloc->invoice?->invoice_no }}</td><td class="right">₱{{ number_format((float) $alloc->amount, 2) }}</td></tr>
            @endforeach
        </table>
    @endif

    <p class="muted" style="margin-top:24px;">This serves as your official receipt. TIN {{ $clinic->tin }}.</p>
</body></html>
