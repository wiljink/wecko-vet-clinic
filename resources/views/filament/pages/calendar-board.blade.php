<x-filament-panels::page>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <label class="text-sm font-medium">Provider</label>
        <select id="calendar-provider"
                class="fi-select-input rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
            <option value="">All providers</option>
            @foreach ($this->getProviders() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <a href="{{ $this->getCreateUrl() }}"
           class="fi-btn fi-btn-size-sm rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white">
            New appointment
        </a>
    </div>

    <div wire:ignore
         id="wecko-calendar"
         data-feed="{{ $this->getFeedUrl() }}"
         class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('wecko-calendar');
            if (!el || el.dataset.booted) return;
            el.dataset.booted = '1';

            const providerSelect = document.getElementById('calendar-provider');
            const feed = el.dataset.feed;

            const calendar = new FullCalendar.Calendar(el, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                },
                nowIndicator: true,
                slotMinTime: '07:00:00',
                slotMaxTime: '20:00:00',
                height: 'auto',
                businessHours: { daysOfWeek: [1, 2, 3, 4, 5, 6], startTime: '08:00', endTime: '18:00' },
                events: function (info, success, failure) {
                    const url = new URL(feed, window.location.origin);
                    url.searchParams.set('start', info.startStr);
                    url.searchParams.set('end', info.endStr);
                    if (providerSelect.value) url.searchParams.set('provider', providerSelect.value);
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json()).then(success).catch(failure);
                },
                eventClick: function (arg) {
                    if (arg.event.url) {
                        arg.jsEvent.preventDefault();
                        window.location.href = arg.event.url;
                    }
                },
                eventDidMount: function (arg) {
                    const p = arg.event.extendedProps;
                    if (p && (p.provider || p.status)) {
                        arg.el.title = [p.provider, p.location, p.status, p.reason].filter(Boolean).join(' · ');
                    }
                },
            });

            calendar.render();
            providerSelect.addEventListener('change', () => calendar.refetchEvents());
        });
    </script>
</x-filament-panels::page>
