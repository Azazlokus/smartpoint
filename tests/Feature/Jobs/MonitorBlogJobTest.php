<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use App\Models\Resource;
use App\Services\Contracts\MonitoringServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class MonitorBlogJobTest extends TestCase
{
    use RefreshDatabase;

    // ── Успешное выполнение ──────────────────────────────────────────────────

    public function test_calls_monitoring_service(): void
    {
        $blog = $this->makeBlog();

        $service = Mockery::mock(MonitoringServiceInterface::class);
        $service->shouldReceive('monitor')->once()->with(
            Mockery::on(fn (Blog $b) => $b->id === $blog->id),
        );

        (new MonitorBlogJob($blog))->handle($service);
    }

    public function test_schedules_next_check_after_success(): void
    {
        $blog = $this->makeBlog(frequencyHours: 6);

        Carbon::setTestNow('2026-01-01 12:00:00');

        $service = Mockery::mock(MonitoringServiceInterface::class);
        $service->shouldReceive('monitor')->once();

        (new MonitorBlogJob($blog))->handle($service);

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'next_check_at' => '2026-01-01 18:00:00',
        ]);

        Carbon::setTestNow();
    }

    // ── Обработка ошибок ─────────────────────────────────────────────────────

    public function test_schedules_next_check_after_final_failure(): void
    {
        $blog = $this->makeBlog(frequencyHours: 4);

        Carbon::setTestNow('2026-01-01 12:00:00');

        (new MonitorBlogJob($blog))->failed(new RuntimeException('Упало'));

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'next_check_at' => '2026-01-01 16:00:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_dispatches_to_monitoring_queue(): void
    {
        $blog = $this->makeBlog();
        $job = new MonitorBlogJob($blog);

        $this->assertSame('monitoring', $job->queue);
    }

    public function test_has_correct_tries_and_backoff(): void
    {
        $blog = $this->makeBlog();
        $job = new MonitorBlogJob($blog);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 120, 300], $job->backoff);
    }

    // ── Вспомогательные методы ───────────────────────────────────────────────

    private function makeBlog(int $frequencyHours = 4): Blog
    {
        $resource = Resource::factory()->mock()->create();

        return Blog::factory()->for($resource)->create([
            'monitor_frequency_hours' => $frequencyHours,
        ]);
    }
}
