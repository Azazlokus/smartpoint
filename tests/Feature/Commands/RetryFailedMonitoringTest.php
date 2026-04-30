<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RetryFailedMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_for_blogs_with_failures(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['monitoring_failures' => 3]);
        Blog::factory()->for($resource)->create(['monitoring_failures' => 1]);

        $this->artisan('monitoring:retry-failed')
            ->assertSuccessful()
            ->expectsOutputToContain('поставлено в очередь блогов для повторного мониторинга: 2');

        Queue::assertPushed(MonitorBlogJob::class, 2);
    }

    public function test_skips_blogs_without_failures(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['monitoring_failures' => 0]);
        Blog::factory()->for($resource)->create(['monitoring_failures' => 2]);

        $this->artisan('monitoring:retry-failed')
            ->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 1);
    }

    public function test_respects_min_failures_option(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['monitoring_failures' => 1]);
        Blog::factory()->for($resource)->create(['monitoring_failures' => 5]);

        $this->artisan('monitoring:retry-failed', ['--min-failures' => 3])
            ->assertSuccessful()
            ->expectsOutputToContain('поставлено в очередь блогов для повторного мониторинга: 1');

        Queue::assertPushed(MonitorBlogJob::class, 1);
    }

    public function test_dry_run_does_not_dispatch_jobs(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['monitoring_failures' => 3]);

        $this->artisan('monitoring:retry-failed', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('(dry-run) найдено блогов для повторного мониторинга: 1');

        Queue::assertNothingPushed();
    }

    public function test_dry_run_does_not_reset_next_check_at(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        $blog = Blog::factory()->for($resource)->create([
            'monitoring_failures' => 2,
            'next_check_at' => now()->addHours(6),
        ]);

        $this->artisan('monitoring:retry-failed', ['--dry-run' => true])->assertSuccessful();

        $this->assertTrue($blog->fresh()->next_check_at->isFuture());
    }

    public function test_resets_next_check_at_to_now(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        $blog = Blog::factory()->for($resource)->create([
            'monitoring_failures' => 1,
            'next_check_at' => now()->addHours(6),
        ]);

        $this->artisan('monitoring:retry-failed')->assertSuccessful();

        $this->assertFalse($blog->fresh()->next_check_at->isFuture());
    }

    public function test_logs_retry_action(): void
    {
        Queue::fake();
        Log::spy();

        $resource = Resource::factory()->mock()->create();
        $blog = Blog::factory()->for($resource)->create(['monitoring_failures' => 2]);

        $this->artisan('monitoring:retry-failed')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->with('monitoring.retry_dispatched', \Mockery::on(
                fn ($ctx) => $ctx['blog_id'] === $blog->id && $ctx['monitoring_failures'] === 2,
            ))
            ->once();
    }

    public function test_reports_nothing_when_no_failed_blogs(): void
    {
        Queue::fake();

        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['monitoring_failures' => 0]);

        $this->artisan('monitoring:retry-failed')
            ->assertSuccessful()
            ->expectsOutputToContain('Блогов с провалами мониторинга не найдено.');

        Queue::assertNothingPushed();
    }

    public function test_skips_blogs_with_inactive_resource(): void
    {
        Queue::fake();

        $active = Resource::factory()->mock()->create(['is_active' => true]);
        $inactive = Resource::factory()->create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);

        Blog::factory()->for($active)->create(['monitoring_failures' => 3]);
        Blog::factory()->for($inactive)->create(['monitoring_failures' => 3]);

        $this->artisan('monitoring:retry-failed')->assertSuccessful();

        Queue::assertPushed(MonitorBlogJob::class, 1);
    }
}
