<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Adapters\BlogSourceAdapter;
use App\Adapters\Contracts\AdapterFactoryInterface;
use App\DTO\BlogMetaDTO;
use App\DTO\PostDTO;
use App\Models\Blog;
use App\Models\Post;
use App\Models\Resource;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class MonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private Blog $blog;

    private BlogSourceAdapter&MockInterface $adapter;

    private MonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = Resource::factory()->mock()->create();
        $this->blog = Blog::factory()->for($resource)->create();

        $this->adapter = Mockery::mock(BlogSourceAdapter::class);

        $factory = Mockery::mock(AdapterFactoryInterface::class);
        $factory->shouldReceive('make')->with('mock')->andReturn($this->adapter);

        $this->service = new MonitoringService($factory);
    }

    // ── Обновление метаданных ────────────────────────────────────────────────

    public function test_updates_blog_meta_from_source(): void
    {
        $meta = new BlogMetaDTO('Новый заголовок', 4.8, 'Luna', 'Вася');
        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($meta);
        $this->adapter->shouldReceive('fetchPosts')->andReturn([]);

        $this->service->monitor($this->blog);

        $this->assertDatabaseHas('blogs', [
            'id' => $this->blog->id,
            'name' => 'Новый заголовок',
            'rating' => 4.8,
            'cat_name' => 'Luna',
            'author' => 'Вася',
        ]);
    }

    // ── Синхронизация постов ─────────────────────────────────────────────────

    public function test_inserts_new_posts(): void
    {
        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_1', 'Первый пост'),
            $this->makePost('post_2', 'Второй пост'),
        ]);

        $this->service->monitor($this->blog);

        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseHas('posts', ['external_id' => 'post_1']);
        $this->assertDatabaseHas('posts', ['external_id' => 'post_2']);
    }

    public function test_updates_existing_posts_on_repeat_monitoring(): void
    {
        // Пост уже есть в БД со старым рейтингом
        Post::factory()->for($this->blog)->create([
            'external_id' => 'post_1',
            'rating' => 1.0,
        ]);

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        // Источник вернул тот же пост с новым рейтингом
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_1', 'Первый пост', rating: 5.0),
        ]);

        $this->service->monitor($this->blog);

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('posts', ['external_id' => 'post_1', 'rating' => 5.0]);
    }

    public function test_deletes_posts_removed_from_source(): void
    {
        Post::factory()->for($this->blog)->create(['external_id' => 'post_old']);

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        // Источник больше не возвращает post_old
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_new', 'Новый пост'),
        ]);

        $this->service->monitor($this->blog);

        $this->assertDatabaseMissing('posts', ['external_id' => 'post_old']);
        $this->assertDatabaseHas('posts', ['external_id' => 'post_new']);
    }

    public function test_upserts_many_posts_in_single_query(): void
    {
        // 50 постов — должен быть один upsert, а не 50 insert
        $posts = array_map(
            fn (int $i) => $this->makePost("post_{$i}", "Пост {$i}"),
            range(1, 50),
        );

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        $this->adapter->shouldReceive('fetchPosts')->andReturn($posts);

        $postInserts = 0;
        DB::listen(static function ($query) use (&$postInserts): void {
            if (
                str_contains(strtolower($query->sql), 'insert') &&
                str_contains(strtolower($query->sql), '"posts"')
            ) {
                $postInserts++;
            }
        });

        $this->service->monitor($this->blog);

        $this->assertDatabaseCount('posts', 50);
        // Один INSERT ... ON DUPLICATE KEY UPDATE вместо 50 отдельных запросов
        $this->assertSame(1, $postInserts);
    }

    // ── Лог мониторинга ──────────────────────────────────────────────────────

    public function test_writes_monitoring_log_after_sync(): void
    {
        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_1', 'Пост'),
        ]);

        $this->service->monitor($this->blog);

        $this->assertDatabaseCount('monitoring_logs', 1);
        $this->assertDatabaseHas('monitoring_logs', ['blog_id' => $this->blog->id]);
    }

    public function test_monitoring_log_contains_only_new_posts(): void
    {
        // Один пост уже существует в БД
        Post::factory()->for($this->blog)->create(['external_id' => 'post_old']);

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_old', 'Старый'),  // уже был
            $this->makePost('post_new', 'Новый'),   // впервые
        ]);

        $this->service->monitor($this->blog);

        $log = $this->blog->monitoringLogs()->first();
        $this->assertCount(1, $log->new_posts);
        $this->assertSame('post_new', $log->new_posts[0]['external_id']);
    }

    public function test_monitoring_log_is_empty_when_no_new_posts(): void
    {
        Post::factory()->for($this->blog)->create(['external_id' => 'post_1']);

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn($this->makeMeta());
        $this->adapter->shouldReceive('fetchPosts')->andReturn([
            $this->makePost('post_1', 'Старый пост'),
        ]);

        $this->service->monitor($this->blog);

        $log = $this->blog->monitoringLogs()->first();
        $this->assertCount(0, $log->new_posts);
    }

    // ── Транзакция ───────────────────────────────────────────────────────────

    public function test_rolls_back_all_changes_on_failure(): void
    {
        $originalName = $this->blog->name;

        $this->adapter->shouldReceive('fetchBlogMeta')->andReturn(
            new BlogMetaDTO('Изменённое имя', 5.0, 'Luna', 'Вася'),
        );
        // fetchPosts бросает исключение — имитируем ошибку в середине цикла
        $this->adapter->shouldReceive('fetchPosts')->andThrow(new RuntimeException('Ошибка источника'));

        $this->expectException(RuntimeException::class);

        try {
            $this->service->monitor($this->blog);
        } finally {
            // Имя блога должно остаться прежним — транзакция откатилась
            $this->assertDatabaseHas('blogs', [
                'id' => $this->blog->id,
                'name' => $originalName,
            ]);
            $this->assertDatabaseCount('monitoring_logs', 0);
        }
    }

    // ── Вспомогательные методы ───────────────────────────────────────────────

    private function makeMeta(): BlogMetaDTO
    {
        return new BlogMetaDTO('Тестовый блог', 4.0, 'Whiskers', 'Автор');
    }

    private function makePost(string $externalId, string $title, float $rating = 3.0): PostDTO
    {
        return new PostDTO(
            externalId: $externalId,
            title: $title,
            body: 'Тело поста',
            rating: $rating,
            reactions: ['likes' => 10, 'loves' => 5, 'wows' => 2, 'hahas' => 1],
        );
    }
}
