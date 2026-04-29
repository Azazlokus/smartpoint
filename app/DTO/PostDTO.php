<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Данные поста, полученные из внешнего источника.
 */
final class PostDTO
{
    /**
     * @param  array<string, int>  $reactions
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $body,
        public readonly float $rating,
        public readonly array $reactions,
    ) {}
}
