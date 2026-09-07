<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1e293b; margin: 20px; }
    h1 { font-size: 16px; margin: 0; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
    th { background: #f1f5f9; text-transform: uppercase; font-size: 9px; }
    td.count { width: 90px; border: 1px solid #94a3b8; }
    .grp { background:#e2e8f0; font-weight:bold; }
</style>
</head>
<body>
    <h1>{{ $clinic->company_name }} — Stock Count Sheet</h1>
    <div class="muted">
        {{ $stockTake->reference }} · Count date {{ optional($stockTake->take_date)->format('d M Y') }}
        · Printed {{ now()->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr><th>Code</th><th>Product</th><th>Pack</th><th class="count">Counted</th></tr>
        </thead>
        <tbody>
        @php($currentGroup = null)
        @foreach($products as $product)
            @if($product->group?->name !== $currentGroup)
                @php($currentGroup = $product->group?->name)
                <tr class="grp"><td colspan="4">{{ $currentGroup ?: 'Ungrouped' }}</td></tr>
            @endif
            <tr>
                <td>{{ $product->code }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ rtrim(rtrim((string) $product->pack_qty, '0'), '.') }}</td>
                <td class="count">&nbsp;</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="muted" style="margin-top:16px;">
        Counted by ______________________ &nbsp;&nbsp; Checked by ______________________ &nbsp;&nbsp; Date __________
    </p>
</body>
</html>
