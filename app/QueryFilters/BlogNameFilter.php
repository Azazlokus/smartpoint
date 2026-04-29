<?php

declare(strict_types=1);

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Поиск по названию блога (нечувствительный к регистру).
 *
 * @implements Filter<\App\Models\Blog>
 */
final class BlogNameFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->where('blogs.name', 'like', '%' . $value . '%');
    }
}
