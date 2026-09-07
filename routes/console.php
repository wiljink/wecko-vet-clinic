<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:generate')->dailyAt('06:00');
Schedule::command('reminders:dispatch --channel=email')->dailyAt('07:00');
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:clean')->dailyAt('02:00');
