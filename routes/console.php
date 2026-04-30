<?php

declare(strict_types=1);

use App\Console\Commands\DispatchMonitoringJobs;
use App\Console\Commands\RetryFailedMonitoring;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    /** @var Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Monitoring Scheduler
|--------------------------------------------------------------------------
|
| Dispatch monitoring jobs every minute. The job itself skips blogs whose
| next_check_at is still in the future, so running this command frequently
| is safe and ensures timely processing even at high blog volumes.
|
*/

Schedule::command(DispatchMonitoringJobs::class)->everyMinute();
Schedule::command(RetryFailedMonitoring::class)->hourly();
