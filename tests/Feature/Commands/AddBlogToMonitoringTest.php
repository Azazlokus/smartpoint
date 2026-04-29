<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddBlogToMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_blog_to_monitoring(): void
    {
        Resource::factory()->mock()->create();

        $this->artisan('blog:add mock blog_42')
            ->expectsOutputToContain('Блог добавлен на мониторинг')
            ->assertSuccessful();

        $this->assertDatabaseHas('blogs', ['external_id' => 'blog_42']);
    }

    public function test_uses_custom_frequency(): void
    {
        Resource::factory()->mock()->create();

        $this->artisan('blog:add mock blog_42 --frequency=8')
            ->assertSuccessful();

        $this->assertDatabaseHas('blogs', [
            'external_id' => 'blog_42',
            'monitor_frequency_hours' => 8,
        ]);
    }

    public function test_fails_for_unknown_resource_slug(): void
    {
        $this->artisan('blog:add unknown_source blog_42')
            ->expectsOutputToContain('не найден')
            ->assertFailed();
    }

    public function test_fails_for_inactive_resource(): void
    {
        Resource::factory()->inactive()->create(['slug' => 'inactive_src']);

        $this->artisan('blog:add inactive_src blog_42')
            ->expectsOutputToContain('не найден')
            ->assertFailed();
    }

    public function test_fails_if_blog_already_monitored(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['external_id' => 'blog_42']);

        $this->artisan('blog:add mock blog_42')
            ->expectsOutputToContain('уже на мониторинге')
            ->assertFailed();
    }

    public function test_fails_for_invalid_frequency(): void
    {
        Resource::factory()->mock()->create();

        $this->artisan('blog:add mock blog_42 --frequency=2')
            ->expectsOutputToContain('от 4 до 8')
            ->assertFailed();
    }

    public function test_sets_next_check_at_immediately(): void
    {
        Resource::factory()->mock()->create();

        $this->artisan('blog:add mock blog_42')->assertSuccessful();

        $blog = Blog::where('external_id', 'blog_42')->first();
        $this->assertNotNull($blog->next_check_at);
    }
}
