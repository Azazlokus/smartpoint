<?php

declare(strict_types=1);

namespace App\Adapters;

use App\DTO\BlogMetaDTO;
use App\DTO\PostDTO;

interface BlogSourceAdapter
{
    /**
     * Возвращает актуальные метаданные блога из внешнего источника.
     */
    public function fetchBlogMeta(string $externalId): BlogMetaDTO;

    /**
     * Возвращает список постов блога из внешнего источника.
     *
     * @return PostDTO[]
     */
    public function fetchPosts(string $externalId): array;
}
