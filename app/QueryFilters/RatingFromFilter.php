<?php

declare(strict_types=1);

namespace App\QueryFilters;

use App\Models\Blog;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Фильтр по минимальному рейтингу блога.
 *
 * @implements Filter<Blog>
 */
final class RatingFromFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->where('blogs.rating', '>=', (float) $value);
    }
}
