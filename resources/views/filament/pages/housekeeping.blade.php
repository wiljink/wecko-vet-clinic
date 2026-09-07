<x-filament-panels::page>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-3 text-sm font-semibold">Backups on file</h3>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-1">File</th><th>Size</th><th>Taken</th></tr></thead>
            <tbody>
            @forelse ($this->getBackups() as $backup)
                <tr class="border-t border-gray-100 dark:border-white/10">
                    <td class="py-1 font-mono text-xs">{{ $backup['name'] }}</td>
                    <td>{{ $backup['size'] }}</td>
                    <td>{{ $backup['date'] }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-gray-500" colspan="3">No backups yet — run one from the button above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="prose prose-sm dark:prose-invert max-w-none rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3>Schedule</h3>
        <p>A database backup runs nightly at 01:30 and old backups are pruned at 02:00
           (<code>routes/console.php</code>, via <code>php artisan schedule:run</code>).
           Backups are written to <code>storage/app/{{ config('backup.backup.name') }}/</code>.
           For off-site safety, sync that folder to an external drive or cloud storage weekly.</p>

        <h3>Restore</h3>
        <p>Restore is a deliberate, rarely-used operation. To restore:</p>
        <ol>
            <li>Stop the application.</li>
            <li>Unzip the chosen backup; it contains <code>db-dumps/mysql-wecko_vet_clinic.sql</code>.</li>
            <li><code>mysql -u root wecko_vet_clinic &lt; mysql-wecko_vet_clinic.sql</code>
                (drop and recreate the schema first for a clean restore).</li>
            <li>Restore the <code>storage/app/public</code> files from the same archive.</li>
            <li><code>php artisan optimize:clear</code> and restart.</li>
        </ol>
        <p>When in doubt, take a fresh backup before restoring an old one.</p>
    </div>
</x-filament-panels::page>
