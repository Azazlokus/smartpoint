<?php

declare(strict_types=1);

namespace App\Adapters;

use App\DTO\BlogMetaDTO;
use App\DTO\PostDTO;

/**
 * Мок-адаптер для тестирования и локальной разработки.
 * Возвращает случайные, но детерминировано структурированные данные
 * без реальных HTTP-запросов к внешним источникам.
 */
final class MockAdapter implements BlogSourceAdapter
{
    private const array CAT_NAMES = [
        'Whiskers', 'Mittens', 'Shadow', 'Luna', 'Oliver',
        'Bella', 'Simba', 'Nala', 'Mochi', 'Cleo',
    ];

    /**
     * Возвращает фиктивные метаданные блога.
     */
    public function fetchBlogMeta(string $externalId): BlogMetaDTO
    {
        return new BlogMetaDTO(
            name: 'Cat Blog '.$externalId,
            rating: round(mt_rand(10, 50) / 10, 1),
            catName: self::CAT_NAMES[array_rand(self::CAT_NAMES)],
            author: 'Author_'.$externalId,
        );
    }

    /**
     * Возвращает от 3 до 8 фиктивных постов блога.
     *
     * @return PostDTO[]
     */
    public function fetchPosts(string $externalId): array
    {
        $posts = [];
        $count = mt_rand(3, 8);

        for ($i = 1; $i <= $count; $i++) {
            $posts[] = new PostDTO(
                externalId: $externalId.'_post_'.$i,
                title: 'Пост '.$i.' о котах — блог '.$externalId,
                body: 'Это содержимое поста '.$i
                    .' из блога '.$externalId
                    .'. Здесь много интересных фактов о котах и их повседневных приключениях.',
                rating: round(mt_rand(10, 50) / 10, 1),
                reactions: [
                    'likes' => mt_rand(0, 200),
                    'loves' => mt_rand(0, 100),
                    'wows' => mt_rand(0, 50),
                    'hahas' => mt_rand(0, 30),
                ],
            );
        }

        return $posts;
    }
}
