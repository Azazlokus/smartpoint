<?php

declare(strict_types=1);

namespace App\Services;

use App\Adapters\Contracts\AdapterFactoryInterface;
use App\DTO\BlogMetaDTO;
use App\DTO\PostDTO;
use App\Models\Blog;
use App\Models\MonitoringLog;
use App\Services\Contracts\MonitoringServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MonitoringService implements MonitoringServiceInterface
{
    public function __construct(
        private readonly AdapterFactoryInterface $adapterFactory,
    ) {}

    public function monitor(Blog $blog): void
    {
        $adapter = $this->adapterFactory->make($blog->resource->slug);

        // Получаем данные из источника ДО открытия транзакции —
        // держать соединение с БД открытым во время сетевого вызова опасно.
        $meta = $adapter->fetchBlogMeta($blog->external_id);
        $posts = $adapter->fetchPosts($blog->external_id);

        // Все записи в БД атомарны: если любой шаг упадёт —
        // блог останется в исходном состоянии и джоб уйдёт на retry.
        $newPosts = [];

        DB::transaction(function () use ($blog, $meta, $posts, &$newPosts): void {
            $this->updateBlogMeta($blog, $meta);

            $newPosts = $this->syncPosts($blog, $posts);

            MonitoringLog::create([
                'blog_id' => $blog->id,
                'date' => Carbon::now(),
                'new_posts' => $newPosts,
            ]);

            // Фиксируем успешный цикл: сбрасываем счётчик неудач.
            $blog->update([
                'last_monitored_at' => Carbon::now(),
                'monitoring_failures' => 0,
            ]);
        });

        Log::info('monitoring.completed', [
            'blog_id' => $blog->id,
            'external_id' => $blog->external_id,
            'source' => $blog->resource->slug,
            'posts_fetched' => count($posts),
            'new_posts' => count($newPosts),
        ]);
    }

    /**
     * Обновляет поля блога из полученных метаданных источника.
     */
    private function updateBlogMeta(Blog $blog, BlogMetaDTO $meta): void
    {
        $blog->update([
            'name' => $meta->name,
            'rating' => $meta->rating,
            'cat_name' => $meta->catName,
            'author' => $meta->author,
        ]);
    }

    /**
     * Upsert полученных постов, удаление исчезнувших, возврат списка новых.
     *
     * @param  PostDTO[]  $posts
     * @return array<int, array{external_id: string, title: string}>
     */
    private function syncPosts(Blog $blog, array $posts): array
    {
        // Собираем external_id постов, которые уже есть в БД до синхронизации
        /** @var string[] $existingExternalIds */
        $existingExternalIds = $blog->posts()->pluck('external_id')->all();
        $fetchedExternalIds = $this->extractExternalIds($posts);

        // Upsert всех полученных постов одним запросом
        if ($posts !== []) {
            DB::table('posts')->upsert(
                $this->buildUpsertRows($blog, $posts),
                ['blog_id', 'external_id'],
                ['title', 'body', 'rating', 'reactions', 'updated_at'],
            );
        }

        // Удаляем посты, которые исчезли из источника
        $toDelete = array_diff($existingExternalIds, $fetchedExternalIds);

        if ($toDelete !== []) {
            $blog->posts()->whereIn('external_id', array_values($toDelete))->delete();
        }

        // Определяем, какие посты появились впервые
        $newExternalIds = array_flip(array_diff($fetchedExternalIds, $existingExternalIds));

        return $this->buildNewPostsList($posts, $newExternalIds);
    }

    /**
     * Извлекает массив external_id из списка DTO.
     *
     * @param  PostDTO[]  $posts
     * @return string[]
     */
    private function extractExternalIds(array $posts): array
    {
        return array_map(self::getExternalId(...), $posts);
    }

    /**
     * Формирует строки для upsert: поля поста + blog_id + временны́е метки.
     *
     * @param  PostDTO[]  $posts
     * @return array<int, array<string, mixed>>
     */
    private function buildUpsertRows(Blog $blog, array $posts): array
    {
        $now = Carbon::now()->toDateTimeString();

        return array_map(
            static fn (PostDTO $post): array => self::postToUpsertRow($blog->id, $post, $now),
            $posts,
        );
    }

    /**
     * Возвращает только те посты, которые появились впервые (не было в БД).
     *
     * @param  PostDTO[]  $posts
     * @param  array<string, int>  $newExternalIds  флип-индекс новых external_id
     * @return array<int, array{external_id: string, title: string}>
     */
    private function buildNewPostsList(array $posts, array $newExternalIds): array
    {
        $onlyNewPosts = array_filter($posts, self::isNewPost($newExternalIds));

        return array_values(array_map(self::postToLogEntry(...), $onlyNewPosts));
    }

    // ── Колбеки ──────────────────────────────────────────────────────────────

    /**
     * Возвращает external_id поста.
     */
    private static function getExternalId(PostDTO $post): string
    {
        return $post->externalId;
    }

    /**
     * Сериализует DTO в строку для upsert-запроса.
     *
     * @return array<string, mixed>
     */
    private static function postToUpsertRow(int $blogId, PostDTO $post, string $now): array
    {
        return [
            'blog_id' => $blogId,
            'external_id' => $post->externalId,
            'title' => $post->title,
            'body' => $post->body,
            'rating' => $post->rating,
            'reactions' => json_encode($post->reactions, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Предикат: пост является новым (отсутствовал в БД до синхронизации).
     *
     * @param  array<string, int>  $newExternalIds
     * @return callable(PostDTO): bool
     */
    private static function isNewPost(array $newExternalIds): callable
    {
        return static fn (PostDTO $post): bool => isset($newExternalIds[$post->externalId]);
    }

    /**
     * Преобразует DTO в краткую запись для лога мониторинга.
     *
     * @return array{external_id: string, title: string}
     */
    private static function postToLogEntry(PostDTO $post): array
    {
        return [
            'external_id' => $post->externalId,
            'title' => $post->title,
        ];
    }
}
