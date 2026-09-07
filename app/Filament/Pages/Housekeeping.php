<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * System Maintenance 9.2 — backup & restore housekeeping.
 */
class Housekeeping extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Backups & Housekeeping';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.housekeeping';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_any_backup') ?? false;
    }

    /** @return array<int, array{name: string, size: string, date: string}> */
    public function getBackups(): array
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $dir = config('backup.backup.name');

        if (! $disk->exists($dir)) {
            return [];
        }

        return collect($disk->files($dir))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->sortDesc()
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => number_format($disk->size($f) / 1048576, 2).' MB',
                'date' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($f))->format('d M Y H:i'),
            ])->values()->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runBackup')
                ->label('Run backup now')->icon('heroicon-m-arrow-down-tray')->requiresConfirmation()
                ->action(function () {
                    abort_unless(Auth::user()->can('create_backup'), 403);
                    try {
                        Artisan::call('backup:run', ['--only-db' => true]);
                        Notification::make()->title('Backup complete')->body(trim(Artisan::output()))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Backup failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
