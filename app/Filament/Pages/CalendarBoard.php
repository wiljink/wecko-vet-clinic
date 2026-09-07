<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CalendarBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Calendar';

    protected static ?string $navigationLabel = 'Appointment Calendar';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.calendar-board';

    protected static ?string $title = 'Appointment Calendar';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_any_appointment') ?? false;
    }

    /** @return array<int, string> */
    public function getProviders(): array
    {
        return User::query()->where('is_provider', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getFeedUrl(): string
    {
        return route('calendar.feed');
    }

    public function getCreateUrl(): string
    {
        return \App\Filament\Resources\AppointmentResource::getUrl('create');
    }
}
