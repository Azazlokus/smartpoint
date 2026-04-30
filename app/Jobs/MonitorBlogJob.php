<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Middleware\ThrottleBySource;
use App\Models\Blog;
use App\Services\Contracts\MonitoringServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MonitorBlogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Максимальное количество попыток выполнения. */
    public int $tries = 3;

    /**
     * Задержки между попытками: 1 мин → 2 мин → 5 мин.
     *
     * @var int[]
     */
    public array $backoff = [60, 120, 300];

    /**
     * Время (в секундах), в течение которого задача считается уникальной.
     * Предотвращает параллельный мониторинг одного блога двумя воркерами.
     */
    public int $uniqueFor = 3600;

    /**
     * Максимальное время выполнения задачи.
     * Защищает воркер от зависания при недоступном источнике.
     */
    public int $timeout = 120;

    /**
     * Если блог был удалён (soft delete) пока джоб ждал в очереди —
     * тихо отбросить задачу вместо падения с "Model not found".
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private readonly Blog $blog,
    ) {
        $this->onQueue('monitoring');
    }

    /**
     * Ключ уникальности — ID блога.
     * Гарантирует, что в очереди не будет двух задач для одного блога одновременно.
     */
    public function uniqueId(): string
    {
        return (string) $this->blog->id;
    }

    /**
     * Мидлвары, применяемые перед выполнением задачи.
     *
     * @return list<ThrottleBySource>
     */
    public function middleware(): array
    {
        return [new ThrottleBySource];
    }

    /**
     * Возвращает блог, связанный с задачей.
     * Используется в мидлваре для получения slug источника.
     */
    public function getBlog(): Blog
    {
        return $this->blog;
    }

    public function handle(MonitoringServiceInterface $monitoringService): void
    {
        $monitoringService->monitor($this->blog);

        $this->scheduleNextCheck();
    }

    /**
     * Вызывается после финальной неудачи задачи (все tries исчерпаны).
     * Сдвигаем next_check_at вперёд и инкрементируем счётчик consecutive-неудач.
     */
    public function failed(Throwable $exception): void
    {
        // Считаем новое значение до increment() — после него модель в памяти
        // не обновляется, и $this->blog->monitoring_failures было бы устаревшим.
        $failuresAfter = $this->blog->monitoring_failures + 1;

        $this->blog->increment('monitoring_failures');
        $this->scheduleNextCheck();

        Log::error('monitoring.failed', [
            'blog_id' => $this->blog->id,
            'external_id' => $this->blog->external_id,
            'source' => $this->blog->resource->slug,
            'monitoring_failures' => $failuresAfter,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Планирует следующую проверку блога, сдвигая next_check_at
     * на monitor_frequency_hours часов вперёд от текущего момента.
     */
    private function scheduleNextCheck(): void
    {
        $this->blog->update([
            'next_check_at' => Carbon::now()->addHours($this->blog->monitor_frequency_hours),
        ]);
    }
}
