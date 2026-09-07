<?php

namespace App\Filament\Pages\Reports;

class RemindersDueReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Reminders Due';

    protected static ?string $title = 'Reminders Due';

    protected static ?int $navigationSort = 10;

    protected string $builderMethod = 'remindersDue';
}
