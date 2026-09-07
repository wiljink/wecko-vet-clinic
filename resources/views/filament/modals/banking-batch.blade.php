<div class="text-sm">
    <p class="mb-2 font-semibold">{{ $batch->reference }} — {{ $batch->banking_date->format('d M Y') }}</p>
    <table class="w-full">
        <thead>
            <tr class="text-left text-gray-500">
                <th class="py-1">Payment</th><th>Client</th><th>Type</th><th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($batch->payments as $p)
            <tr class="border-t border-gray-100 dark:border-white/10">
                <td class="py-1">{{ $p->payment_no }}</td>
                <td>{{ $p->client?->full_name }}</td>
                <td>{{ \App\Models\Payment::TYPES[$p->payment_type] ?? $p->payment_type }}</td>
                <td class="text-right">₱{{ number_format((float) $p->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-300 font-semibold">
                <td class="py-1" colspan="3">Grand total</td>
                <td class="text-right">₱{{ number_format((float) $batch->grand_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
