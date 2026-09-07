<div class="text-sm">
    <p class="mb-2 text-gray-500">{{ $activity->description }} · {{ $activity->created_at->format('d M Y H:i:s') }}</p>
    @php($props = $activity->properties)
    @if (isset($props['old']) || isset($props['attributes']))
        <table class="w-full">
            <thead><tr class="text-left text-gray-500"><th class="py-1">Field</th><th>Old</th><th>New</th></tr></thead>
            <tbody>
            @foreach (($props['attributes'] ?? []) as $key => $new)
                <tr class="border-t border-gray-100 dark:border-white/10">
                    <td class="py-1 font-medium">{{ $key }}</td>
                    <td class="text-gray-500">{{ \Illuminate\Support\Str::limit((string) data_get($props, "old.$key"), 60) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $new, 60) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <pre class="whitespace-pre-wrap text-xs">{{ json_encode($props, JSON_PRETTY_PRINT) }}</pre>
    @endif
</div>
