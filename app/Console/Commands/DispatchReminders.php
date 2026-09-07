<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\ReminderDispatcher;
use Illuminate\Console\Command;

/**
 * Sends every reminder that is due today or overdue, on each client's preferred
 * channel (manual 4.2.1). --pretend reports the counts without sending.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--channel=email : email|sms|letter} {--pretend}';

    protected $description = 'Send due patient reminders on the given channel';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $channel = $this->option('channel');
        $due = Reminder::due()->with('client', 'patient', 'reminderType')->get();

        [$issued, $sent] = $dispatcher->sendPatientReminders($due, $channel, (bool) $this->option('pretend'));

        $this->info($this->option('pretend')
            ? "{$issued} reminder(s) would be sent by {$channel} (pretend)."
            : "{$sent} of {$issued} eligible reminder(s) sent by {$channel}.");

        return self::SUCCESS;
    }
}
