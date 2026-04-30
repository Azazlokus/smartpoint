<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Тесты Swagger / OpenAPI документации.
 *
 * Проверяют, что сгенерированный JSON-файл корректен и содержит
 * все ожидаемые эндпоинты и схемы, — без обращения к реальным HTTP-маршрутам.
 */
final class SwaggerTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $path = storage_path('api-docs/api-docs.json');

        if (! file_exists($path)) {
            $this->artisan('l5-swagger:generate');
        }

        $this->spec = json_decode(file_get_contents($path), true);
    }

    // ── Метаданные ────────────────────────────────────────────────────────────

    public function test_spec_has_correct_openapi_version(): void
    {
        $this->assertSame('3.0.0', $this->spec['openapi']);
    }

    public function test_spec_has_info_block(): void
    {
        $this->assertArrayHasKey('info', $this->spec);
        $this->assertNotEmpty($this->spec['info']['title']);
        $this->assertNotEmpty($this->spec['info']['version']);
    }

    public function test_spec_has_server(): void
    {
        $this->assertNotEmpty($this->spec['servers']);
        $this->assertSame('/api/v1', $this->spec['servers'][0]['url']);
    }

    // ── Пути ─────────────────────────────────────────────────────────────────

    #[DataProvider('endpointProvider')]
    public function test_endpoint_exists(string $path, string $method): void
    {
        $this->assertArrayHasKey($path, $this->spec['paths'], "Путь {$path} не найден в спецификации");
        $this->assertArrayHasKey($method, $this->spec['paths'][$path], "Метод {$method} не найден для {$path}");
    }

    /** @return array<string, array{string, string}> */
    public static function endpointProvider(): array
    {
        return [
            'GET /blogs' => ['/blogs',      'get'],
            'POST /blogs' => ['/blogs',      'post'],
            'GET /blogs/{id}' => ['/blogs/{id}', 'get'],
            'DELETE /blogs/{id}' => ['/blogs/{id}', 'delete'],
        ];
    }

    // ── HTTP-коды ─────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $responses = $this->spec['paths']['/blogs']['get']['responses'];
        $this->assertArrayHasKey('200', $responses);
    }

    public function test_store_returns_201_and_422(): void
    {
        $responses = $this->spec['paths']['/blogs']['post']['responses'];
        $this->assertArrayHasKey('201', $responses, 'POST /blogs должен описывать ответ 201');
        $this->assertArrayHasKey('422', $responses, 'POST /blogs должен описывать ответ 422');
    }

    public function test_show_returns_200_and_404(): void
    {
        $responses = $this->spec['paths']['/blogs/{id}']['get']['responses'];
        $this->assertArrayHasKey('200', $responses);
        $this->assertArrayHasKey('404', $responses);
    }

    public function test_destroy_returns_200_and_404(): void
    {
        $responses = $this->spec['paths']['/blogs/{id}']['delete']['responses'];
        $this->assertArrayHasKey('200', $responses);
        $this->assertArrayHasKey('404', $responses);
    }

    // ── Схемы ─────────────────────────────────────────────────────────────────

    #[DataProvider('schemaProvider')]
    public function test_schema_exists(string $schema): void
    {
        $this->assertArrayHasKey(
            $schema,
            $this->spec['components']['schemas'],
            "Схема {$schema} не найдена в components/schemas",
        );
    }

    /** @return array<string, array{string}> */
    public static function schemaProvider(): array
    {
        return [
            'Blog' => ['Blog'],
            'BlogCollection' => ['BlogCollection'],
            'BlogResponse' => ['BlogResponse'],
            'ResourceObject' => ['ResourceObject'],
            'StoreBlogRequest' => ['StoreBlogRequest'],
            'ValidationError' => ['ValidationError'],
        ];
    }

    public function test_blog_schema_has_required_fields(): void
    {
        $props = $this->spec['components']['schemas']['Blog']['properties'];

        foreach (['id', 'external_id', 'name', 'rating', 'monitor_frequency_hours', 'resource'] as $field) {
            $this->assertArrayHasKey($field, $props, "Поле {$field} отсутствует в схеме Blog");
        }
    }

    public function test_store_request_schema_has_required_fields(): void
    {
        $schema = $this->spec['components']['schemas']['StoreBlogRequest'];
        $required = $schema['required'] ?? [];

        $this->assertContains('resource_id', $required);
        $this->assertContains('external_id', $required);
        $this->assertContains('monitor_frequency_hours', $required);
    }

    public function test_store_request_frequency_has_min_max(): void
    {
        $freq = $this->spec['components']['schemas']['StoreBlogRequest']['properties']['monitor_frequency_hours'];

        $this->assertSame(4, $freq['minimum']);
        $this->assertSame(8, $freq['maximum']);
    }

    public function test_resource_object_schema_has_slug(): void
    {
        $props = $this->spec['components']['schemas']['ResourceObject']['properties'];

        $this->assertArrayHasKey('slug', $props);
    }

    // ── Тег Blogs ─────────────────────────────────────────────────────────────

    public function test_all_endpoints_have_blogs_tag(): void
    {
        $endpoints = [
            ['/blogs', 'get'],
            ['/blogs', 'post'],
            ['/blogs/{id}', 'get'],
            ['/blogs/{id}', 'delete'],
        ];

        foreach ($endpoints as [$path, $method]) {
            $tags = $this->spec['paths'][$path][$method]['tags'] ?? [];
            $this->assertContains('Blogs', $tags, "{$method} {$path} должен иметь тег Blogs");
        }
    }

    // ── Ссылки $ref ───────────────────────────────────────────────────────────

    public function test_post_request_body_refs_store_blog_request(): void
    {
        $ref = $this->spec['paths']['/blogs']['post']['requestBody']['content']['application/json']['schema']['$ref'];
        $this->assertSame('#/components/schemas/StoreBlogRequest', $ref);
    }

    public function test_get_blogs_response_refs_blog_collection(): void
    {
        $ref = $this->spec['paths']['/blogs']['get']['responses']['200']['content']['application/json']['schema']['$ref'];
        $this->assertSame('#/components/schemas/BlogCollection', $ref);
    }

    public function test_blog_collection_has_pagination_and_items(): void
    {
        $props = $this->spec['components']['schemas']['BlogCollection']['properties']['data']['properties'];
        $this->assertArrayHasKey('pagination', $props);
        $this->assertArrayHasKey('items', $props);
    }

    public function test_blog_collection_pagination_has_required_fields(): void
    {
        $paginationProps = $this->spec['components']['schemas']['BlogCollection']['properties']['data']['properties']['pagination']['properties'];
        foreach (['page', 'per_page', 'total', 'last_page'] as $field) {
            $this->assertArrayHasKey($field, $paginationProps, "Поле pagination.{$field} отсутствует в схеме BlogCollection");
        }
    }

    public function test_store_and_show_responses_ref_blog_response(): void
    {
        $storeRef = $this->spec['paths']['/blogs']['post']['responses']['201']['content']['application/json']['schema']['$ref'];
        $showRef = $this->spec['paths']['/blogs/{id}']['get']['responses']['200']['content']['application/json']['schema']['$ref'];

        $this->assertSame('#/components/schemas/BlogResponse', $storeRef);
        $this->assertSame('#/components/schemas/BlogResponse', $showRef);
    }
}
