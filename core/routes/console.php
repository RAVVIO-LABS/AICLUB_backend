<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Reminder\ClassSessionReminderService;
use App\Services\Reminder\FreeClassReminderService;
use App\Services\BatchAutoCancelService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('sessions:send-reminders', function (ClassSessionReminderService $service) {
    $stats = $service->sendDueReminders();
    $this->info("Processed sessions: {$stats['sessions']}, emails sent: {$stats['emails']}");
})->purpose('Send enrolled student class reminders one hour before each scheduled session');

Artisan::command('free-class:send-reminders', function (FreeClassReminderService $service) {
    $sent24h = $service->send24HourReminders();
    $sent1h = $service->send1HourReminders();
    $this->info("Free class reminders sent -> 24h: {$sent24h}, 1h: {$sent1h}");
})->purpose('Send free class 24-hour and 1-hour reminders');

Artisan::command('batches:autocancel-empty', function (BatchAutoCancelService $service) {
    $stats = $service->cancelExpiredEmptyBatches();
    $this->info('Auto-cancelled batches -> live: ' . $stats['live'] . ', zoom: ' . $stats['zoom'] . ', teachers released: ' . $stats['teachers_released']);
})->purpose('Auto-cancel empty batches after their start date');

Schedule::command('sessions:send-reminders')->everyMinute();
Schedule::command('free-class:send-reminders')->everyMinute();
Schedule::command('batches:autocancel-empty')->everyThirtyMinutes();
