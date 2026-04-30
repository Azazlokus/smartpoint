<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class DispatchMonitoringJobs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitoring:dispatch';

    /**
     * @var string
     */
    protected $description = 'Диспатчит MonitorBlogJob для всех блогов, у которых наступило время проверки';

    public function handle(): int
    {
        $dispatched = 0;

        Blog::query()
            ->where('next_check_at', '<=', Carbon::now())
            ->whereHas('resource', fn ($q) => $q->where('is_active', true))
            ->with('resource')
            ->chunkById(100, function ($blogs) use (&$dispatched): void {
                foreach ($blogs as $blog) {
                    MonitorBlogJob::dispatch($blog);
                    $dispatched++;
                }
            });

        $this->info("Отправлено задач мониторинга: {$dispatched}.");

        return self::SUCCESS;
    }
}
