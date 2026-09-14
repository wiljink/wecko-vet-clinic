<x-filament-panels::page>
    <style>
        .imp-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1.5rem; }
        .imp-card { border-radius: 0.75rem; background: rgb(255 255 255); padding: 1rem; margin-top: 1.5rem;
            box-shadow: 0 0 0 1px rgb(0 0 0 / 0.05); }
        .dark .imp-card { background: rgb(17 24 39); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
        .imp-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; text-align: center; margin-top: 0.75rem; }
        .imp-tile { border-radius: 0.5rem; padding: 0.75rem; background: rgb(249 250 251); }
        .dark .imp-tile { background: rgb(255 255 255 / 0.05); }
        .imp-num { font-size: 1.5rem; font-weight: 700; }
        .imp-lbl { font-size: 0.75rem; color: rgb(107 114 128); }
        .imp-err { margin-top: 1rem; }
        .imp-err li { font-size: 0.875rem; color: rgb(185 28 28); }
        .imp-commit { margin-top: 1rem; border-top: 1px solid rgb(243 244 246); padding-top: 1rem; }
    </style>

    <form wire:submit="runPreview">
        {{ $this->form }}

        <div class="imp-actions">
            <x-filament::button type="button" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="downloadTemplate">
                Download template
            </x-filament::button>
            <x-filament::button type="submit" icon="heroicon-m-eye" wire:loading.attr="disabled" wire:target="runPreview">
                Preview import
            </x-filament::button>
        </div>
    </form>

    @if ($preview)
        <div class="imp-card">
            <h3 style="font-size:0.875rem;font-weight:600;">{{ $preview->dryRun ? 'Preview — nothing saved yet' : 'Result' }}</h3>

            <div class="imp-tiles">
                <div class="imp-tile"><div class="imp-num" style="color:rgb(21 128 61);">{{ $preview->created }}</div><div class="imp-lbl">created</div></div>
                <div class="imp-tile"><div class="imp-num" style="color:rgb(29 78 216);">{{ $preview->updated }}</div><div class="imp-lbl">updated</div></div>
                <div class="imp-tile"><div class="imp-num" style="color:rgb(75 85 99);">{{ $preview->skipped }}</div><div class="imp-lbl">skipped</div></div>
            </div>

            @if ($preview->errors)
                <div class="imp-err">
                    <h4 style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:rgb(220 38 38);">Issues</h4>
                    <ul>
                        @foreach ($preview->errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($preview->dryRun)
                <div class="imp-commit">
                    <p class="imp-lbl" style="margin-bottom:0.5rem;">
                        Commit these {{ $preview->created + $preview->updated }} change(s) to the database.
                    </p>
                    <x-filament::button color="success" icon="heroicon-m-check" wire:click="runImport"
                        wire:loading.attr="disabled" wire:target="runImport">
                        Commit import
                    </x-filament::button>
                </div>
            @else
                <p style="margin-top:1rem;font-size:0.875rem;font-weight:500;color:rgb(21 128 61);">{{ $preview->summary() }}</p>
            @endif
        </div>
    @endif
</x-filament-panels::page>
