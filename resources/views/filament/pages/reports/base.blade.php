<x-filament-panels::page>
    <form wire:submit="generate" class="flex flex-wrap items-end gap-4">
        {{ $this->form }}
        <x-filament::button type="submit">Generate</x-filament::button>
    </form>

    @php($report = $this->report)

    <div class="text-sm text-gray-500">{{ $report['subtitle'] ?? '' }}</div>

    @if (! empty($report['tiles']))
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($report['tiles'] as $tile)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-xs uppercase text-gray-500">{{ $tile['label'] }}</div>
                    <div class="mt-1 text-lg font-semibold">
                        {{ \App\Support\Reports\Cell::display($tile['type'] ?? 'text', $tile['value']) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @foreach ($report['sections'] as $section)
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold dark:border-white/10">{{ $section['title'] }}</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            @foreach ($section['columns'] as $col)
                                <th class="px-4 py-2 {{ \App\Support\Reports\Cell::alignRight($col) ? 'text-right' : '' }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section['rows'] as $row)
                            <tr class="border-t border-gray-50 dark:border-white/5">
                                @foreach ($section['columns'] as $i => $col)
                                    <td class="px-4 py-2 {{ \App\Support\Reports\Cell::alignRight($col) ? 'text-right' : '' }}">
                                        {{ \App\Support\Reports\Cell::display($col['type'] ?? 'text', $row[$i] ?? null) }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="px-4 py-3 text-gray-500" colspan="{{ count($section['columns']) }}">No data for this period.</td></tr>
                        @endforelse
                        @if (! empty($section['total']))
                            <tr class="border-t-2 border-gray-300 font-semibold">
                                @foreach ($section['columns'] as $i => $col)
                                    <td class="px-4 py-2 {{ \App\Support\Reports\Cell::alignRight($col) ? 'text-right' : '' }}">
                                        {{ \App\Support\Reports\Cell::display($col['type'] ?? 'text', $section['total'][$i] ?? null) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @foreach ($report['notes'] ?? [] as $note)
        <p class="text-xs text-gray-500">{{ $note }}</p>
    @endforeach
</x-filament-panels::page>
