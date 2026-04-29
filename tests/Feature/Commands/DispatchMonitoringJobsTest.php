<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DispatchMonitoringJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // ── Основная логика диспатча ─────────────────────────────────────────────

    public function test_dispatches_jobs_for_due_blogs(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->due()->count(3)->create();

        $this->artisan('monitoring:dispatch')->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 3);
    }

    public function test_skips_blogs_not_yet_due(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->notDue()->count(5)->create();

        $this->artisan('monitoring:dispatch')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_dispatches_only_due_blogs_when_mixed(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->due()->count(2)->create();
        Blog::factory()->for($resource)->notDue()->count(3)->create();

        $this->artisan('monitoring:dispatch')->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 2);
    }

    // ── Фильтрация по is_active ──────────────────────────────────────────────

    public function test_skips_blogs_from_inactive_resources(): void
    {
        $active = Resource::factory()->mock()->create();
        $inactive = Resource::factory()->inactive()->create();

        Blog::factory()->for($active)->due()->count(2)->create();
        Blog::factory()->for($inactive)->due()->count(3)->create();

        $this->artisan('monitoring:dispatch')->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 2);
    }

    // ── Нагрузочный тест: tens of thousands ─────────────────────────────────

    public function test_dispatches_all_due_blogs_in_chunks(): void
    {
        $resource = Resource::factory()->mock()->create();

        // Создаём 500 блогов: 300 просроченных, 200 ещё не пора
        Blog::factory()->for($resource)->due()->count(300)->create();
        Blog::factory()->for($resource)->notDue()->count(200)->create();

        $this->artisan('monitoring:dispatch')
            ->expectsOutputToContain('300')
            ->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 300);
    }

    public function test_handles_thousand_due_blogs_without_memory_issues(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->due()->count(1000)->create();

        $memoryBefore = memory_get_usage();

        $this->artisan('monitoring:dispatch')->assertSuccessful();

        $memoryAfter = memory_get_usage();

        Queue::assertPushed(MonitorBlogJob::class, 1000);

        // chunkById(100) не должен заметно увеличивать память (< 20 МБ)
        $this->assertLessThan(20 * 1024 * 1024, $memoryAfter - $memoryBefore);
    }

    // ── Вывод команды ────────────────────────────────────────────────────────

    public function test_outputs_dispatched_count(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->due()->count(4)->create();

        $this->artisan('monitoring:dispatch')
            ->expectsOutputToContain('4')
            ->assertSuccessful();
    }

    public function test_outputs_zero_when_nothing_to_dispatch(): void
    {
        $this->artisan('monitoring:dispatch')
            ->expectsOutputToContain('0')
            ->assertSuccessful();
    }
}
