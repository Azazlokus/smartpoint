<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\MockAdapter;
use App\DTO\BlogMetaDTO;
use App\DTO\PostDTO;
use Tests\TestCase;

final class MockAdapterTest extends TestCase
{
    private MockAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new MockAdapter;
    }

    public function test_fetch_blog_meta_returns_blog_meta_dto(): void
    {
        $meta = $this->adapter->fetchBlogMeta('blog_1');

        $this->assertInstanceOf(BlogMetaDTO::class, $meta);
        $this->assertStringContainsString('blog_1', $meta->name);
        $this->assertGreaterThan(0, $meta->rating);
        $this->assertNotEmpty($meta->catName);
        $this->assertNotEmpty($meta->author);
    }

    public function test_fetch_posts_returns_array_of_post_dtos(): void
    {
        $posts = $this->adapter->fetchPosts('blog_1');

        $this->assertNotEmpty($posts);
        $this->assertContainsOnlyInstancesOf(PostDTO::class, $posts);
    }

    public function test_fetch_posts_returns_between_3_and_8_posts(): void
    {
        // Запускаем несколько раз — количество постов случайное (3–8)
        $counts = [];
        for ($i = 0; $i < 20; $i++) {
            $counts[] = count($this->adapter->fetchPosts('blog_x'));
        }

        $this->assertGreaterThanOrEqual(3, min($counts));
        $this->assertLessThanOrEqual(8, max($counts));
    }

    public function test_post_dto_contains_required_fields(): void
    {
        $post = $this->adapter->fetchPosts('blog_1')[0];

        $this->assertNotEmpty($post->externalId);
        $this->assertNotEmpty($post->title);
        $this->assertNotEmpty($post->body);
        $this->assertGreaterThan(0, $post->rating);
        $this->assertArrayHasKey('likes', $post->reactions);
        $this->assertArrayHasKey('loves', $post->reactions);
    }
}
