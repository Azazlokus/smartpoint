<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Fluent;
use Spatie\QueryBuilder\QueryBuilder;
use Webmozart\Assert\Assert;

/**
 * Лёгкий пагинатор для API-ответов.
 *
 * Оборачивает Eloquent/QueryBuilder-запрос в стандартизированный ответ
 * с блоком `pagination` и массивом `items`, трансформированным через JsonResource.
 */
final class ApiNanoPaginator
{
    private const DEFAULT_PER_PAGE = 20;

    private const DEFAULT_PAGE = 1;

    private const MAX_PER_PAGE = 100;

    /**
     * Выполняет запрос с пагинацией и трансформирует результат через JsonResource.
     *
     * @template TModel of Model
     * @param  Builder<TModel>|QueryBuilder<TModel>  $queryBuilder
     * @param  class-string<JsonResource>            $resourceClass
     * @return array<string, mixed>
     */
    public static function paginate(
        Builder|QueryBuilder $queryBuilder,
        string $resourceClass,
        ?int $page,
        ?int $perPage,
        ?Request $request = null,
    ): array {
        $request ??= request();

        Assert::subclassOf($resourceClass, JsonResource::class, 'ApiNanoPaginator поддерживает только JsonResource-классы');

        /** @var Fluent<string, int> $parameters */
        $parameters = new Fluent(validator(
            [
                'page'     => $page     ?? self::DEFAULT_PAGE,
                'per_page' => $perPage  ?? self::DEFAULT_PER_PAGE,
            ],
            [
                'page'     => ['integer', 'min:1'],
                'per_page' => ['integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            ]
        )->validate());

        $result = $queryBuilder->paginate(
            $parameters->get('per_page'),
            ['*'],
            'page',
            $parameters->get('page'),
        );

        return [
            'pagination' => [
                'page'      => $parameters->get('page'),
                'per_page'  => $result->perPage(),
                'total'     => $result->total(),
                'last_page' => $result->lastPage(),
            ],
            'items' => $resourceClass::collection($result->items())->toArray($request),
        ];
    }
}
