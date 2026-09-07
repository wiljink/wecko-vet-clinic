<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\LeaveVacationBreak;
use App\Models\NationalHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('view_any_appointment'), 403);

        $from = $request->date('start') ?? now()->startOfMonth();
        $to = $request->date('end') ?? now()->endOfMonth();
        $providerId = $request->integer('provider') ?: null;

        $events = Appointment::query()
            ->between($from, $to)
            ->when($providerId, fn ($q) => $q->where('provider_id', $providerId))
            ->with(['client', 'patient', 'provider', 'location', 'status', 'label'])
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'title' => trim(($a->patient?->name ? $a->patient->name.' — ' : '').($a->client?->full_name ?? 'Appointment')),
                'start' => $a->starts_at->toIso8601String(),
                'end' => $a->ends_at->toIso8601String(),
                'allDay' => $a->all_day,
                'backgroundColor' => $a->color,
                'borderColor' => $a->color,
                'url' => route('filament.admin.resources.appointments.edit', $a),
                'extendedProps' => [
                    'provider' => $a->provider?->name,
                    'location' => $a->location?->name,
                    'status' => $a->status?->name,
                    'reason' => $a->reason?->reason,
                ],
            ]);

        $blocks = LeaveVacationBreak::query()
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->with('user')
            ->get()
            ->map(fn (LeaveVacationBreak $l) => [
                'id' => 'leave-'.$l->id,
                'title' => $l->user?->name.' — '.ucfirst($l->type),
                'start' => $l->starts_at->toIso8601String(),
                'end' => $l->ends_at->toIso8601String(),
                'display' => 'background',
                'backgroundColor' => '#fca5a5',
            ]);

        $holidays = NationalHoliday::query()
            ->whereBetween('holiday_date', [$from, $to])
            ->get()
            ->map(fn (NationalHoliday $h) => [
                'id' => 'holiday-'.$h->id,
                'title' => $h->name,
                'start' => $h->holiday_date->toDateString(),
                'allDay' => true,
                'display' => 'background',
                'backgroundColor' => '#fed7aa',
            ]);

        return response()->json($events->concat($blocks)->concat($holidays)->values());
    }
}
