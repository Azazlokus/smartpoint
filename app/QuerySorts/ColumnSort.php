<?php

declare(strict_types=1);

namespace App\QuerySorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Универсальная сортировка по произвольной колонке с фиксированным направлением.
 *
 * Используется для именованных сортировок, где направление задаётся явно
 * (например, `rating_desc` всегда сортирует по убыванию).
 */
final class ColumnSort implements Sort
{
    public function __construct(
        private readonly string $column,
        private readonly string $direction = 'asc',
    ) {}

    /** @param Builder<Model> $query */
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $query->orderBy($this->column, $this->direction);
    }
}
