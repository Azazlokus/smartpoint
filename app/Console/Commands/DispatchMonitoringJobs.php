<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class DispatchMonitoringJobs extends Command
{
    private const string LOCK_KEY = 'monitoring:dispatch';

    private const int LOCK_TTL_SECONDS = 120;

    /**
     * @var string
     */
    protected $signature = 'monitoring:dispatch
        {--dry-run : Показать блоги без реального диспатча}';

    /**
     * @var string
     */
    protected $description = 'Диспатчит MonitorBlogJob для всех блогов, у которых наступило время проверки';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->warn('Команда уже выполняется. Пропускаем.');

            return self::SUCCESS;
        }

        try {
            return $this->dispatch();
        } finally {
            $lock->release();
        }
    }

    private function dispatch(): int
    {
        $startedAt = microtime(true);
        $dispatched = 0;
        $dryRun = (bool) $this->option('dry-run');

        Blog::query()
            ->dueForMonitoring()
            ->withActiveResource()
            ->with('resource')
            ->chunkById(100, function (Collection $blogs) use (&$dispatched, $dryRun): void {
                foreach ($blogs as $blog) {
                    if (! $dryRun) {
                        MonitorBlogJob::dispatch($blog);
                    }

                    $dispatched++;
                }
            });

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        Log::info('monitoring.dispatch_completed', [
            'dispatched' => $dispatched,
            'duration_ms' => $durationMs,
            'dry_run' => $dryRun,
        ]);

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info("Отправлено задач мониторинга: {$dispatched}{$suffix}. Время: {$durationMs}мс.");

        return self::SUCCESS;
    }
}
