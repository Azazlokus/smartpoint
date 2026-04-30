<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BlogIndexRequest;
use App\Http\Requests\StoreBlogRequest;
use App\Http\Resources\BlogResource;
use App\Http\Resources\MonitoringLogResource;
use App\Models\Blog;
use App\Models\MonitoringLog;
use App\Models\Resource;
use App\QueryFilters\BlogNameFilter;
use App\QueryFilters\RatingFromFilter;
use App\QuerySorts\ColumnSort;
use App\Support\Api;
use App\Support\ApiNanoPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class BlogController extends Controller
{
    /**
     * Список блогов с фильтрацией, сортировкой и пагинацией.
     */
    #[OA\Get(
        path: '/blogs',
        summary: 'Список блогов',
        description: <<<'DESC'
        Возвращает список блогов на мониторинге.

        **Фильтрация** (`filter[поле]=значение`):
        - `filter[name]` — поиск по названию блога (LIKE)
        - `filter[resource_id]` — точное совпадение по ID источника
        - `filter[rating_from]` — минимальный рейтинг (≥ значение)

        **Сортировка** (`sort=значение`):
        `rating_asc`, `rating_desc`, `name_asc`, `name_desc`, `newest`

        **Пагинация**: `page` и `per_page` (1–100, по умолчанию 20).
        DESC,
        tags: ['Blogs'],
        parameters: [
            new OA\Parameter(name: 'filter[name]', in: 'query', required: false, description: 'Поиск по названию', schema: new OA\Schema(type: 'string', example: 'Мурзик')),
            new OA\Parameter(name: 'filter[resource_id]', in: 'query', required: false, description: 'ID источника', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'filter[rating_from]', in: 'query', required: false, description: 'Минимальный рейтинг', schema: new OA\Schema(type: 'number', example: 4.0)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Сортировка', schema: new OA\Schema(type: 'string', enum: ['rating_asc', 'rating_desc', 'name_asc', 'name_desc', 'newest'], example: 'rating_desc')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Записей на страницу', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Номер страницы', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список блогов', content: new OA\JsonContent(ref: '#/components/schemas/BlogCollection')),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function index(BlogIndexRequest $request): JsonResponse
    {
        $query = QueryBuilder::for(Blog::with('resource'))
            ->allowedFilters([
                AllowedFilter::custom('name', new BlogNameFilter),
                AllowedFilter::exact('resource_id'),
                AllowedFilter::custom('rating_from', new RatingFromFilter),
            ])
            ->allowedSorts([
                AllowedSort::custom('rating_asc', new ColumnSort('rating', 'asc'), 'rating_asc'),
                AllowedSort::custom('rating_desc', new ColumnSort('rating', 'desc'), 'rating_desc'),
                AllowedSort::custom('name_asc', new ColumnSort('name', 'asc'), 'name_asc'),
                AllowedSort::custom('name_desc', new ColumnSort('name', 'desc'), 'name_desc'),
                AllowedSort::custom('newest', new ColumnSort('created_at', 'desc'), 'newest'),
            ])
            ->defaultSort('-id');

        $data = ApiNanoPaginator::paginate(
            $query,
            BlogResource::class,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
            $request,
        );

        return Api::success('Список блогов получен.', $data);
    }

    /**
     * Добавить блог на мониторинг.
     */
    #[OA\Post(
        path: '/blogs',
        summary: 'Добавить блог',
        description: 'Добавляет блог на мониторинг. Первая проверка будет выполнена немедленно.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreBlogRequest'),
        ),
        tags: ['Blogs'],
        responses: [
            new OA\Response(response: 201, description: 'Блог успешно добавлен', content: new OA\JsonContent(ref: '#/components/schemas/BlogResponse')),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreBlogRequest $request): JsonResponse
    {
        $resource = Resource::findOrFail($request->integer('resource_id'));

        $blog = Blog::create([
            'resource_id' => $resource->id,
            'external_id' => $request->string('external_id'),
            'name' => $request->string('external_id'),
            'rating' => 0.0,
            'monitor_frequency_hours' => $request->integer('monitor_frequency_hours'),
            'next_check_at' => Carbon::now(),
        ]);

        $blog->load('resource');

        return Api::created('Блог добавлен на мониторинг.', (new BlogResource($blog))->toArray($request));
    }

    /**
     * Детали одного блога.
     */
    #[OA\Get(
        path: '/blogs/{id}',
        summary: 'Один блог',
        description: 'Возвращает детали одного блога по его ID.',
        tags: ['Blogs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID блога', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Блог найден', content: new OA\JsonContent(ref: '#/components/schemas/BlogResponse')),
            new OA\Response(response: 404, description: 'Блог не найден'),
        ],
    )]
    public function show(Blog $blog): JsonResponse
    {
        $blog->load('resource');

        return Api::success('Блог найден.', (new BlogResource($blog))->toArray(request()));
    }

    /**
     * История мониторинга блога.
     */
    #[OA\Get(
        path: '/blogs/{id}/logs',
        summary: 'История мониторинга',
        description: 'Возвращает записи журнала мониторинга для блога, отсортированные от новых к старым.',
        tags: ['Blogs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID блога', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Записей на страницу', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Номер страницы', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Логи мониторинга', content: new OA\JsonContent(ref: '#/components/schemas/MonitoringLogCollection')),
            new OA\Response(response: 404, description: 'Блог не найден'),
        ],
    )]
    public function logs(Blog $blog, Request $request): JsonResponse
    {
        $query = MonitoringLog::query()
            ->where('blog_id', $blog->id)
            ->orderByDesc('date');

        $data = ApiNanoPaginator::paginate(
            $query,
            MonitoringLogResource::class,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
            $request,
        );

        return Api::success('Логи мониторинга получены.', $data);
    }

    /**
     * Снять блог с мониторинга.
     */
    #[OA\Delete(
        path: '/blogs/{id}',
        summary: 'Удалить блог',
        description: 'Снимает блог с мониторинга (удаляет из системы).',
        tags: ['Blogs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID блога', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Блог снят с мониторинга',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Блог снят с мониторинга.')],
                ),
            ),
            new OA\Response(response: 404, description: 'Блог не найден'),
        ],
    )]
    public function destroy(Blog $blog): JsonResponse
    {
        $blog->delete();

        return Api::success('Блог снят с мониторинга.');
    }
}
