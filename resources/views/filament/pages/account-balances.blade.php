<x-filament-panels::page>
    {{ $this->form }}

    @php($ledger = $this->ledger)

    @if ($ledger)
        @php($client = $this->client)

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            @foreach (\App\Support\ClientLedger::agingLabels() + ['total' => 'Total owing'] as $key => $label)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-xs uppercase text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-lg font-semibold {{ $key === 'total' ? 'text-primary-600' : '' }}">
                        ₱{{ number_format((float) $ledger['aging'][$key], 2) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('clients.statement', ['client' => $client->id]) }}" target="_blank"
               class="fi-btn rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white">Print statement</a>
            <a href="{{ \App\Filament\Resources\PaymentResource::getUrl('create') }}?client={{ $client->id }}"
               class="fi-btn rounded-lg bg-white px-3 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:bg-white/5 dark:ring-white/10">Record payment</a>
            <a href="{{ \App\Filament\Resources\AccountAdjustmentResource::getUrl('create') }}"
               class="fi-btn rounded-lg bg-white px-3 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:bg-white/5 dark:ring-white/10">New adjustment</a>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-white/10">
                        <th class="p-3">Date</th><th>Type</th><th>Reference</th><th>Particulars</th>
                        <th class="text-right">Debit</th><th class="text-right">Credit</th><th class="p-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-100 text-gray-500 dark:border-white/10">
                        <td class="p-3" colspan="6">Opening balance</td>
                        <td class="p-3 text-right">₱{{ number_format($ledger['statement']['opening'], 2) }}</td>
                    </tr>
                    @forelse ($ledger['statement']['rows'] as $row)
                        <tr class="border-b border-gray-50 dark:border-white/5">
                            <td class="p-3">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['reference'] }}</td>
                            <td>{{ $row['particulars'] }}</td>
                            <td class="text-right">{{ $row['debit'] ? '₱'.number_format($row['debit'], 2) : '' }}</td>
                            <td class="text-right">{{ $row['credit'] ? '₱'.number_format($row['credit'], 2) : '' }}</td>
                            <td class="p-3 text-right">₱{{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3 text-gray-500" colspan="7">No transactions in the last 6 months.</td></tr>
                    @endforelse
                    <tr class="border-t border-gray-300 font-semibold">
                        <td class="p-3" colspan="6">Current balance</td>
                        <td class="p-3 text-right">₱{{ number_format($ledger['statement']['closing'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Choose a client to see their account.</p>
    @endif
</x-filament-panels::page>
