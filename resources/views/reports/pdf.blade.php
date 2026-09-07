<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1e293b; margin: 26px; }
    h1 { font-size: 16px; margin: 0; }
    .muted { color: #64748b; }
    .tiles { margin: 10px 0; }
    .tile { display: inline-block; width: 23%; padding: 6px 8px; border: 1px solid #cbd5e1; margin-right: 6px; }
    .tile .l { font-size: 8px; text-transform: uppercase; color: #64748b; }
    .tile .v { font-size: 12px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; }
    .r { text-align: right; }
    .tot td { border-top: 2px solid #94a3b8; font-weight: bold; }
</style></head><body>
    <div style="float:right;text-align:right;" class="muted">{{ $clinic->company_name }}<br>{{ $clinic->phone }}</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="muted">{{ $report['subtitle'] ?? '' }} · Generated {{ $generatedAt->format('d M Y H:i') }}</div>

    @if (! empty($report['tiles']))
        <div class="tiles">
            @foreach ($report['tiles'] as $tile)
                <span class="tile"><span class="l">{{ $tile['label'] }}</span><br>
                    <span class="v">{{ \App\Support\Reports\Cell::display($tile['type'] ?? 'text', $tile['value']) }}</span></span>
            @endforeach
        </div>
    @endif

    @foreach ($report['sections'] as $section)
        <h3 style="font-size:11px;margin:14px 0 2px;">{{ $section['title'] }}</h3>
        <table>
            <thead><tr>
                @foreach ($section['columns'] as $col)
                    <th class="{{ \App\Support\Reports\Cell::alignRight($col) ? 'r' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr></thead>
            <tbody>
            @foreach ($section['rows'] as $row)
                <tr>
                    @foreach ($section['columns'] as $i => $col)
                        <td class="{{ \App\Support\Reports\Cell::alignRight($col) ? 'r' : '' }}">
                            {{ \App\Support\Reports\Cell::display($col['type'] ?? 'text', $row[$i] ?? null) }}</td>
                    @endforeach
                </tr>
            @endforeach
            @if (! empty($section['total']))
                <tr class="tot">
                    @foreach ($section['columns'] as $i => $col)
                        <td class="{{ \App\Support\Reports\Cell::alignRight($col) ? 'r' : '' }}">
                            {{ \App\Support\Reports\Cell::display($col['type'] ?? 'text', $section['total'][$i] ?? null) }}</td>
                    @endforeach
                </tr>
            @endif
            </tbody>
        </table>
    @endforeach

    @foreach ($report['notes'] ?? [] as $note)
        <p class="muted" style="font-size:8px;">{{ $note }}</p>
    @endforeach
</body></html>
