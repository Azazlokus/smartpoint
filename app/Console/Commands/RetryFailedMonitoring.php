<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class RetryFailedMonitoring extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitoring:retry-failed
        {--min-failures=1 : Минимальное количество последовательных провалов для перезапуска}
        {--dry-run : Показать блоги без реального перезапуска}';

    /**
     * @var string
     */
    protected $description = 'Повторно ставит в очередь мониторинг блогов, превысивших порог последовательных ошибок';

    public function handle(): int
    {
        $minFailures = (int) $this->option('min-failures');
        $dryRun = (bool) $this->option('dry-run');

        $blogs = Blog::query()
            ->where('monitoring_failures', '>=', $minFailures)
            ->withActiveResource()
            ->with('resource')
            ->get();

        if ($blogs->isEmpty()) {
            $this->info('Блогов с провалами мониторинга не найдено.');

            return self::SUCCESS;
        }

        foreach ($blogs as $blog) {
            $this->line("  [{$blog->id}] {$blog->external_id} (failures: {$blog->monitoring_failures})");

            if (! $dryRun) {
                $blog->update(['next_check_at' => Carbon::now()]);
                MonitorBlogJob::dispatch($blog);

                Log::info('monitoring.retry_dispatched', [
                    'blog_id' => $blog->id,
                    'external_id' => $blog->external_id,
                    'source' => $blog->resource->slug,
                    'monitoring_failures' => $blog->monitoring_failures,
                ]);
            }
        }

        $label = $dryRun ? '(dry-run) найдено' : 'поставлено в очередь';
        $this->info("{$label} блогов для повторного мониторинга: {$blogs->count()}.");

        return self::SUCCESS;
    }
}
