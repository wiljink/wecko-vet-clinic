<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 10mm; }
        body { margin: 0; }
        .label {
            display: inline-block;
            width: 60mm;
            height: 30mm;
            padding: 2mm;
            margin: 0 1mm 1mm 0;
            border: 1px dashed #cbd5e1;
            text-align: center;
            vertical-align: top;
            overflow: hidden;
        }
        .name { font-size: 9px; font-weight: bold; height: 22px; overflow: hidden; }
        .price { font-size: 12px; font-weight: bold; margin: 1mm 0; }
        .barcode img { height: 44px; }
        .muted { color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            <div class="name">{{ $label['name'] }}</div>
            <div class="price">{{ $clinic->currency_symbol }}{{ $label['price'] }}</div>
            <div class="barcode"><img src="{{ $label['svg'] }}" alt="{{ $label['barcode'] }}"></div>
            <div class="muted">{{ $clinic->company_name }} @if ($label['code']) &middot; {{ $label['code'] }} @endif</div>
        </div>
    @endforeach
</body>
</html>
