<x-filament-panels::page>
    <form wire:submit="run">
        {{ $this->form }}
        <div class="mt-6">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                Run statements
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        <h3 class="mb-2 text-sm font-semibold text-gray-500">Recent runs</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-white/10">
                    <th class="py-2">Run</th><th>Period</th><th>Fee</th><th>Channel</th><th class="text-right">Statements</th>
                </tr>
            </thead>
            <tbody>
            @foreach (\App\Models\StatementRun::latest()->take(10)->get() as $run)
                <tr class="border-b border-gray-50 dark:border-white/5">
                    <td class="py-2">{{ $run->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $run->from_date->format('d M') }} – {{ $run->to_date->format('d M Y') }}</td>
                    <td>{{ $run->fee_type === 'none' ? '—' : ucfirst($run->fee_type).' '.$run->fee_value }}</td>
                    <td>{{ ucfirst($run->channel) }}</td>
                    <td class="text-right">{{ $run->statement_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
