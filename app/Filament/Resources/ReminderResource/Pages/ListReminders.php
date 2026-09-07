<?php

namespace App\Filament\Resources\ReminderResource\Pages;

use App\Filament\Resources\ReminderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReminders extends ListRecords
{
    protected static string $resource = ReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate reminders')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Scans vaccination boosters, patients reaching de-sexing age and product reminder intervals, and queues any new reminders.')
                ->action(function () {
                    abort_unless(auth()->user()->can('run_reminders'), 403);
                    $count = \Illuminate\Support\Facades\Artisan::call('reminders:generate');
                    Notification::make()->title('Reminder generation complete')->body(trim(\Illuminate\Support\Facades\Artisan::output()))->success()->send();
                }),
        ];
    }
}
