<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Middleware;

use App\Jobs\Middleware\ThrottleBySource;
use App\Jobs\MonitorBlogJob;
use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class ThrottleBySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_job_through_when_limit_not_reached(): void
    {
        $blog = $this->blogWithResource();
        $nextCalled = false;

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog), function () use (&$nextCalled): void {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }

    public function test_does_not_call_next_when_rate_limit_exhausted(): void
    {
        $blog = $this->blogWithResource();

        $key = 'adapter:'.$blog->resource->slug;
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 60);
        }

        $nextCalled = false;

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog), function () use (&$nextCalled): void {
            $nextCalled = true;
        });

        $this->assertFalse($nextCalled);
    }

    public function test_different_sources_have_independent_limits(): void
    {
        $resource1 = Resource::factory()->mock()->create();
        $resource2 = Resource::factory()->create(['name' => 'Other', 'slug' => 'other']);

        $blog1 = Blog::factory()->for($resource1)->create();
        $blog2 = Blog::factory()->for($resource2)->create();

        // Exhaust limit only for source 1
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('adapter:'.$resource1->slug, 60);
        }

        $next1Called = false;
        $next2Called = false;

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog1), function () use (&$next1Called): void {
            $next1Called = true;
        });

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog2), function () use (&$next2Called): void {
            $next2Called = true;
        });

        $this->assertFalse($next1Called);
        $this->assertTrue($next2Called);
    }

    public function test_increments_rate_limiter_on_each_pass(): void
    {
        $blog = $this->blogWithResource();
        $key = 'adapter:'.$blog->resource->slug;

        $this->assertSame(0, RateLimiter::attempts($key));

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog), fn () => null);
        $this->assertSame(1, RateLimiter::attempts($key));

        (new ThrottleBySource)->handle(new MonitorBlogJob($blog), fn () => null);
        $this->assertSame(2, RateLimiter::attempts($key));
    }

    private function blogWithResource(): Blog
    {
        $resource = Resource::factory()->mock()->create();

        return Blog::factory()->for($resource)->create();
    }
}
