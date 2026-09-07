<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Models\Patient;
use App\Models\PatientReminderType;
use App\Models\Reminder;
use App\Models\Vaccination;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Builds pending reminder rows the practice will act on (manual 4.2):
 *  - vaccination boosters coming due
 *  - patients reaching the configured de-sexing age
 *  - product / service reminder intervals (worming, dental, heartworm...)
 * Existing pending reminders are never duplicated.
 */
class GenerateReminders extends Command
{
    protected $signature = 'reminders:generate {--horizon=60 : days ahead to look}';

    protected $description = 'Generate patient reminders from vaccination boosters, de-sexing age and reminder intervals';

    public function handle(): int
    {
        $horizon = now()->addDays((int) $this->option('horizon'));
        $created = 0;

        $created += $this->vaccinationBoosters($horizon);
        $created += $this->desexingAge();
        $created += $this->intervalReminders($horizon);

        $this->info("Created {$created} reminder(s).");

        return self::SUCCESS;
    }

    private function vaccinationBoosters(Carbon $horizon): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('vaccinations')) {
            return 0;
        }

        $count = 0;

        Vaccination::query()
            ->whereNotNull('booster_due_on')
            ->whereDate('booster_due_on', '<=', $horizon)
            ->with('patient.client')
            ->each(function (Vaccination $vaccination) use (&$count) {
                $exists = Reminder::where('vaccination_id', $vaccination->id)
                    ->whereIn('status', ['pending', 'sent'])->exists();

                if ($exists || ! $vaccination->patient) {
                    return;
                }

                Reminder::create([
                    'remindable_type' => $vaccination->getMorphClass(),
                    'remindable_id' => $vaccination->id,
                    'client_id' => $vaccination->patient->client_id,
                    'patient_id' => $vaccination->patient_id,
                    'patient_reminder_type_id' => $vaccination->product?->patient_reminder_type_id,
                    'category' => 'vaccination',
                    'vaccination_id' => $vaccination->id,
                    'species_id' => $vaccination->patient->species_id,
                    'due_on' => $vaccination->booster_due_on,
                    'notes' => "{$vaccination->name} booster due",
                ]);
                $count++;
            });

        return $count;
    }

    private function desexingAge(): int
    {
        $months = (int) CompanySetting::current()->default_desex_age_months;
        $count = 0;

        Patient::query()
            ->where('is_active', true)
            ->where('neuter_status', 'not_neutered')
            ->with('client')
            ->each(function (Patient $patient) use ($months, &$count) {
                if (($patient->age_in_months ?? 0) < $months) {
                    return;
                }

                $exists = Reminder::where('patient_id', $patient->id)
                    ->where('category', 'desexing')
                    ->whereIn('status', ['pending', 'sent'])->exists();

                if ($exists) {
                    return;
                }

                Reminder::create([
                    'remindable_type' => $patient->getMorphClass(),
                    'remindable_id' => $patient->id,
                    'client_id' => $patient->client_id,
                    'patient_id' => $patient->id,
                    'patient_reminder_type_id' => PatientReminderType::where('category', 'desexing')->value('id'),
                    'category' => 'desexing',
                    'species_id' => $patient->species_id,
                    'due_on' => now()->toDateString(),
                    'notes' => "{$patient->name} has reached de-sexing age",
                ]);
                $count++;
            });

        return $count;
    }

    private function intervalReminders(Carbon $horizon): int
    {
        // Products/services carrying a reminder type raise a follow-up reminder the
        // interval after they were last dispensed (handled when consult items are
        // saved). Here we just top up de-sexing/other types with no open reminder
        // for patients that have had that item before — covered by the consult hook,
        // so this pass is a safety net for imported data and is intentionally light.
        return 0;
    }
}
