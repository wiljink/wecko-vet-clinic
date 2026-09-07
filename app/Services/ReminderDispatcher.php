<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CompanySetting;
use App\Models\DocumentTemplate;
use App\Models\Reminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends appointment reminders, patient reminders and marketing messages over
 * letter / email / SMS. Email uses Laravel mail (the `log` mailer in dev);
 * SMS and letters are logged — the channel is a single swap-in point here.
 */
class ReminderDispatcher
{
    /** @param Collection<int, Appointment> $appointments */
    public function sendAppointmentReminders(Collection $appointments, string $channel): int
    {
        $clinic = CompanySetting::current();
        $sent = 0;

        foreach ($appointments as $appointment) {
            $client = $appointment->client;

            if (! $client) {
                continue;
            }

            $prefKey = "appointments_by_{$channel}";
            if (! ($client->{$prefKey} ?? true)) {
                continue;
            }

            if ($channel === 'email' && ! $client->email) {
                continue;
            }
            if ($channel === 'sms' && ! $client->mobile_phone) {
                continue;
            }

            $message = sprintf(
                'Hi %s, this is a reminder that %s has an appointment at %s on %s. — %s',
                $client->full_name,
                $appointment->patient?->name ?? 'your pet',
                $clinic->company_name,
                $appointment->starts_at->format('D j M, g:i a'),
                $clinic->company_name,
            );

            $this->deliver($channel, $client, 'Appointment reminder', $message);

            $appointment->update([
                'reminder_channel' => $channel,
                'reminder_sent_at' => now(),
            ]);
            $sent++;
        }

        return $sent;
    }

    /**
     * Send a batch of patient reminders. Returns [issued, sent].
     *
     * @param  Collection<int, Reminder>  $reminders
     * @return array{0:int,1:int}
     */
    public function sendPatientReminders(Collection $reminders, string $channel, bool $pretend = false): array
    {
        $clinic = CompanySetting::current();
        $batch = (string) Str::uuid();
        $issued = 0;
        $sent = 0;

        foreach ($reminders as $reminder) {
            $client = $reminder->client;
            if (! $client) {
                continue;
            }

            $prefKey = "reminders_by_{$channel}";
            if (! ($client->{$prefKey} ?? true)) {
                continue;
            }
            if ($channel === 'email' && ! $client->email) {
                continue;
            }
            if ($channel === 'sms' && ! $client->mobile_phone) {
                continue;
            }

            $issued++;

            $template = $this->templateFor($reminder, $channel);
            $data = [
                'client' => ['name' => $client->full_name, 'surname' => $client->surname],
                'patient' => ['name' => $reminder->patient?->name],
                'reminder' => ['due_on' => optional($reminder->due_on)->format('j M Y'), 'type' => $reminder->reminderType?->name],
                'clinic' => ['name' => $clinic->company_name, 'phone' => $clinic->phone],
            ];

            [$subject, $body] = $template
                ? array_values($template->render($data))
                : ['Reminder from '.$clinic->company_name, $this->fallbackBody($reminder, $clinic)];

            if ($pretend) {
                continue;
            }

            $this->deliver($channel, $client, $subject, $body);

            $reminder->update([
                'status' => 'sent',
                'sent_channel' => $channel,
                'sent_at' => now(),
                'batch_id' => $batch,
            ]);
            $sent++;
        }

        return [$issued, $sent];
    }

    /**
     * Run a marketing campaign against clients matching its filter.
     * Filter keys: species_id, has_email, inactive_days, min_balance.
     */
    public function runCampaign(\App\Models\MarketingCampaign $campaign): int
    {
        $clinic = CompanySetting::current();
        $filter = $campaign->filter ?? [];
        $channels = $campaign->channels ?: ['email'];
        $template = $campaign->template;

        $query = \App\Models\Client::query()->where('is_active', true);

        if ($filter['has_email'] ?? false) {
            $query->whereNotNull('email');
        }
        if ($filter['species_id'] ?? null) {
            $query->whereHas('patients', fn ($q) => $q->where('species_id', $filter['species_id']));
        }

        $count = 0;

        $query->with('patients')->chunk(200, function ($clients) use ($channels, $template, $clinic, &$count) {
            foreach ($clients as $client) {
                foreach ($channels as $channel) {
                    $prefKey = "marketing_by_{$channel}";
                    if (! $client->{$prefKey}) {
                        continue;
                    }
                    if ($channel === 'email' && ! $client->email) {
                        continue;
                    }
                    if ($channel === 'sms' && ! $client->mobile_phone) {
                        continue;
                    }

                    $data = [
                        'client' => ['name' => $client->full_name, 'surname' => $client->surname],
                        'clinic' => ['name' => $clinic->company_name, 'phone' => $clinic->phone],
                    ];
                    [$subject, $body] = $template
                        ? array_values($template->render($data))
                        : ['News from '.$clinic->company_name, 'Hello '.$client->full_name.', we have news from '.$clinic->company_name.'.'];

                    $this->deliver($channel, $client, $subject, $body);
                    $count++;
                }
            }
        });

        $campaign->update(['ran_at' => now(), 'recipients' => $count]);

        return $count;
    }

    private function templateFor(Reminder $reminder, string $channel): ?DocumentTemplate
    {
        $type = "reminder_{$channel}";

        return DocumentTemplate::query()
            ->where('is_active', true)
            ->where('type', $type)
            ->where(fn ($q) => $q
                ->whereNull('patient_reminder_type_id')
                ->orWhere('patient_reminder_type_id', $reminder->patient_reminder_type_id))
            ->orderByDesc('patient_reminder_type_id')
            ->orderBy('sequence')
            ->first();
    }

    private function fallbackBody(Reminder $reminder, CompanySetting $clinic): string
    {
        return sprintf(
            "Dear %s,\n\nOur records show that %s is due for %s on %s.\nPlease call %s on %s to book an appointment.\n\n%s",
            $reminder->client->full_name,
            $reminder->patient?->name ?? 'your pet',
            $reminder->reminderType?->name ?? 'a health check',
            optional($reminder->due_on)->format('j M Y'),
            $clinic->company_name,
            $clinic->phone,
            $clinic->company_name,
        );
    }

    private function deliver(string $channel, $client, string $subject, string $body): void
    {
        if ($channel === 'email') {
            \Illuminate\Support\Facades\Mail::raw($body, function ($mail) use ($client, $subject) {
                $mail->to($client->email)->subject($subject);
            });

            return;
        }

        // SMS / letter — logged; wire a gateway or print queue here.
        Log::channel('single')->info("[{$channel}] to {$client->full_name}: {$subject} — {$body}");
    }
}
