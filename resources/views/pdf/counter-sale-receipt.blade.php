@php
    $cur = $clinic->currency_symbol ?: '₱';
    $payment = optional($sale->invoice)->allocations->first()->payment ?? null;
    $money = fn ($v) => $cur.number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; padding: 6px 8px; font-size: 10px; color: #000; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        hr { border: none; border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .r { text-align: right; }
        .muted { color: #333; }
    </style>
</head>
<body>
    <div class="c b" style="font-size:12px;">{{ $clinic->company_name }}</div>
    @if ($clinic->address)<div class="c muted">{{ $clinic->address }}</div>@endif
    @if ($clinic->phone)<div class="c muted">Tel: {{ $clinic->phone }}</div>@endif
    @if ($clinic->tin)<div class="c muted">TIN: {{ $clinic->tin }}</div>@endif
    @if ($clinic->receipt_header)<div class="c">{{ $clinic->receipt_header }}</div>@endif
    <hr>

    <div>Sale: <span class="b">{{ $sale->sale_no }}</span></div>
    <div>{{ $sale->created_at->format('d M Y H:i') }}</div>
    <div>Served by: {{ $sale->provider?->name ?? '—' }}</div>
    <div>Customer: {{ $sale->client?->full_name ?? ($sale->walk_in_name ?: 'Walk-in') }}</div>
    <hr>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2">{{ $item->description }}</td>
            </tr>
            <tr>
                <td class="muted">
                    {{ rtrim(rtrim(number_format((float) $item->qty, 2), '0'), '.') }} &times; {{ $money($item->unit_price_ex_tax) }}
                    @if ($item->discount_pct > 0) (-{{ rtrim(rtrim(number_format((float) $item->discount_pct, 2), '0'), '.') }}%) @endif
                </td>
                <td class="r">{{ $money($item->line_total_inc_tax) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>

    <table>
        <tr><td>Subtotal (ex {{ $clinic->tax_label }})</td><td class="r">{{ $money($sale->subtotal_ex_tax) }}</td></tr>
        @if ($sale->discount_total > 0)
            <tr><td>Discount</td><td class="r">-{{ $money($sale->discount_total) }}</td></tr>
        @endif
        <tr><td>{{ $clinic->tax_label }} ({{ rtrim(rtrim(number_format((float) $clinic->tax_rate, 2), '0'), '.') }}%)</td><td class="r">{{ $money($sale->tax_total) }}</td></tr>
        <tr class="b"><td>TOTAL</td><td class="r">{{ $money($sale->total_inc_tax) }}</td></tr>
    </table>
    <hr>

    @if ($payment)
        <table>
            <tr><td>Paid ({{ \App\Models\Payment::TYPES[$payment->payment_type] ?? ucfirst($payment->payment_type) }}@if ($payment->cardType) / {{ $payment->cardType->name }}@endif)</td>
                <td class="r">{{ $money($payment->amount) }}</td></tr>
            @if ($payment->cash_received)
                <tr><td>Cash</td><td class="r">{{ $money($payment->cash_received) }}</td></tr>
                <tr class="b"><td>Change</td><td class="r">{{ $money($payment->change_given) }}</td></tr>
            @endif
        </table>
    @else
        <div class="b">CHARGED TO ACCOUNT</div>
    @endif
    <hr>

    <div class="c">{{ $clinic->receipt_footer ?: 'Thank you for your business!' }}</div>
    <div class="c muted">This serves as your official receipt.</div>
</body>
</html>
