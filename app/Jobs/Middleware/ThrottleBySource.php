<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Jobs\MonitorBlogJob;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Ограничивает количество одновременных запросов к одному источнику.
 *
 * Проблема: при 10 воркерах все они могут одновременно обратиться
 * к одному источнику (mock, catster и т.д.), что приведёт к бану по IP
 * или rate limit-ошибкам со стороны источника.
 *
 * Решение: не более MAX_ATTEMPTS запросов к одному slug за DECAY_SECONDS секунд.
 * Если лимит исчерпан — задача возвращается в очередь через RELEASE_DELAY секунд.
 */
final class ThrottleBySource
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    private const int RELEASE_DELAY = 30;

    /**
     * @param  MonitorBlogJob  $job
     * @param  callable(MonitorBlogJob): void  $next
     */
    public function handle(MonitorBlogJob $job, callable $next): void
    {
        $key = 'adapter:' . $job->getBlog()->resource->slug;

        $executed = RateLimiter::attempt(
            key: $key,
            maxAttempts: self::MAX_ATTEMPTS,
            callback: fn () => $next($job),
            decaySeconds: self::DECAY_SECONDS,
        );

        if (! $executed) {
            // Лимит исчерпан — вернуть задачу в очередь, попробовать позже.
            $job->release(self::RELEASE_DELAY);
        }
    }
}
