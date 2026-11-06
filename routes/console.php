<?php

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shop:backup', function (BackupService $backups) {
    $file = $backups->create();
    $this->info("Backup saved: {$file}");
})->purpose('Back up the shop database to storage/app/backups');

/**
 * Shared hosting: add ONE cron job that runs every minute:
 *   php /path/to/artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('shop:backup')->dailyAt('02:00');
