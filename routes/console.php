<?php

declare(strict_types=1);

use App\Console\Commands\DispatchMonitoringJobs;
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
| Планировщик мониторинга
|--------------------------------------------------------------------------
|
| Диспатчит задачи мониторинга каждую минуту. Команда выбирает только те
| блоги, у которых наступило время проверки (next_check_at <= now()), поэтому
| частый запуск безопасен и обеспечивает точность планирования при большом
| количестве блогов.
|
*/

Schedule::command(DispatchMonitoringJobs::class)->everyMinute();
