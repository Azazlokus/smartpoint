<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Метаданные блога, полученные из внешнего источника.
 */
final class BlogMetaDTO
{
    public function __construct(
        public readonly string $name,
        public readonly float $rating,
        public readonly ?string $catName,
        public readonly ?string $author,
    ) {}
}
