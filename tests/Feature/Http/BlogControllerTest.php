<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/blogs ────────────────────────────────────────────────────

    public function test_index_returns_paginated_blogs(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->count(3)->create();

        $this->getJson('/api/v1/blogs')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'pagination' => ['page', 'per_page', 'total', 'last_page'],
                    'items' => [['id', 'external_id', 'name', 'resource']],
                ],
            ]);
    }

    public function test_index_respects_per_page(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->count(5)->create();

        $this->getJson('/api/v1/blogs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5);
    }

    public function test_index_validates_per_page_max(): void
    {
        $this->getJson('/api/v1/blogs?per_page=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    // ── Фильтрация ────────────────────────────────────────────────────────────

    public function test_filter_by_name(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['name' => 'Мурзик daily']);
        Blog::factory()->for($resource)->create(['name' => 'Барсик news']);

        $this->getJson('/api/v1/blogs?filter[name]=Мурзик')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Мурзик daily');
    }

    public function test_filter_by_resource_id(): void
    {
        $source1 = Resource::factory()->mock()->create();
        $source2 = Resource::factory()->create(['name' => 'Other', 'slug' => 'other']);
        Blog::factory()->for($source1)->count(2)->create();
        Blog::factory()->for($source2)->count(3)->create();

        $this->getJson("/api/v1/blogs?filter[resource_id]={$source1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_filter_by_rating_from(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['name' => 'Топ',     'rating' => 4.8]);
        Blog::factory()->for($resource)->create(['name' => 'Средний', 'rating' => 3.2]);
        Blog::factory()->for($resource)->create(['name' => 'Низкий',  'rating' => 1.5]);

        $this->getJson('/api/v1/blogs?filter[rating_from]=4')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Топ');
    }

    public function test_filters_can_be_combined(): void
    {
        $source1 = Resource::factory()->mock()->create();
        $source2 = Resource::factory()->create(['name' => 'Other', 'slug' => 'other']);
        Blog::factory()->for($source1)->create(['name' => 'Мурзик топ',     'rating' => 4.9]);
        Blog::factory()->for($source1)->create(['name' => 'Мурзик простой', 'rating' => 2.0]);
        Blog::factory()->for($source2)->create(['name' => 'Мурзик чужой',   'rating' => 4.9]);

        $this->getJson("/api/v1/blogs?filter[name]=Мурзик&filter[resource_id]={$source1->id}&filter[rating_from]=4")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Мурзик топ');
    }

    // ── Сортировка ────────────────────────────────────────────────────────────

    public function test_sort_by_rating_desc(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['name' => 'Низкий', 'rating' => 1.0]);
        Blog::factory()->for($resource)->create(['name' => 'Высокий', 'rating' => 5.0]);
        Blog::factory()->for($resource)->create(['name' => 'Средний', 'rating' => 3.0]);

        $names = collect($this->getJson('/api/v1/blogs?sort=rating_desc')->assertOk()->json('data.items'))
            ->pluck('name')->all();

        $this->assertSame(['Высокий', 'Средний', 'Низкий'], $names);
    }

    public function test_sort_by_name_asc(): void
    {
        $resource = Resource::factory()->mock()->create();
        Blog::factory()->for($resource)->create(['name' => 'Яблоко']);
        Blog::factory()->for($resource)->create(['name' => 'Арбуз']);
        Blog::factory()->for($resource)->create(['name' => 'Мандарин']);

        $names = collect($this->getJson('/api/v1/blogs?sort=name_asc')->assertOk()->json('data.items'))
            ->pluck('name')->all();

        $this->assertSame(['Арбуз', 'Мандарин', 'Яблоко'], $names);
    }

    public function test_sort_invalid_value_returns_422(): void
    {
        $this->getJson('/api/v1/blogs?sort=invalid_sort')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    // ── POST /api/v1/blogs ───────────────────────────────────────────────────

    public function test_store_creates_blog_and_returns_it(): void
    {
        $resource = Resource::factory()->mock()->create();

        $this->postJson('/api/v1/blogs', [
            'resource_id' => $resource->id,
            'external_id' => 'blog_99',
            'monitor_frequency_hours' => 6,
        ])
            ->assertCreated()
            ->assertJsonPath('data.external_id', 'blog_99')
            ->assertJsonPath('data.resource.slug', 'mock');

        $this->assertDatabaseHas('blogs', [
            'resource_id' => $resource->id,
            'external_id' => 'blog_99',
        ]);
    }

    public function test_store_sets_next_check_at_to_now(): void
    {
        $resource = Resource::factory()->mock()->create();

        $this->postJson('/api/v1/blogs', [
            'resource_id' => $resource->id,
            'external_id' => 'blog_99',
            'monitor_frequency_hours' => 4,
        ])->assertCreated();

        $blog = Blog::where('external_id', 'blog_99')->first();
        $this->assertNotNull($blog->next_check_at);
        $this->assertTrue($blog->next_check_at->isPast() || $blog->next_check_at->isCurrentMinute());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/blogs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resource_id', 'external_id', 'monitor_frequency_hours']);
    }

    public function test_store_validates_frequency_range(): void
    {
        $resource = Resource::factory()->mock()->create();

        $this->postJson('/api/v1/blogs', [
            'resource_id' => $resource->id,
            'external_id' => 'blog_99',
            'monitor_frequency_hours' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['monitor_frequency_hours']);
    }

    public function test_store_validates_resource_exists(): void
    {
        $this->postJson('/api/v1/blogs', [
            'resource_id' => 99999,
            'external_id' => 'blog_99',
            'monitor_frequency_hours' => 4,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['resource_id']);
    }

    // ── GET /api/v1/blogs/{id} ───────────────────────────────────────────────

    public function test_show_returns_blog(): void
    {
        $resource = Resource::factory()->mock()->create();
        $blog = Blog::factory()->for($resource)->create();

        $this->getJson("/api/v1/blogs/{$blog->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $blog->id)
            ->assertJsonPath('data.resource.id', $resource->id);
    }

    public function test_show_returns_404_for_unknown_blog(): void
    {
        $this->getJson('/api/v1/blogs/99999')->assertNotFound();
    }

    // ── DELETE /api/v1/blogs/{id} ────────────────────────────────────────────

    public function test_destroy_removes_blog(): void
    {
        $resource = Resource::factory()->mock()->create();
        $blog = Blog::factory()->for($resource)->create();

        $this->deleteJson("/api/v1/blogs/{$blog->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Блог снят с мониторинга.');

        $this->assertDatabaseMissing('blogs', ['id' => $blog->id]);
    }
}
