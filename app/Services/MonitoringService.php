<?php

declare(strict_types=1);

namespace App\Services;

use App\Adapters\Contracts\AdapterFactoryInterface;
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

        // DB::transaction возвращает значение из колбека — reference не нужен.
        // Все записи в БД атомарны: если любой шаг упадёт —
        // блог останется в исходном состоянии и джоб уйдёт на retry.
        $newPosts = DB::transaction(function () use ($blog, $meta, $posts): array {
            $now = Carbon::now();

            $newPosts = $this->syncPosts($blog, $posts);

            MonitoringLog::create([
                'blog_id' => $blog->id,
                'date' => $now,
                'new_posts' => $newPosts,
            ]);

            // Мета источника + сброс счётчика неудач — одним UPDATE вместо двух.
            $blog->update([
                'name' => $meta->name,
                'rating' => $meta->rating,
                'cat_name' => $meta->catName,
                'author' => $meta->author,
                'last_monitored_at' => $now,
                'monitoring_failures' => 0,
            ]);

            return $newPosts;
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
     * Upsert полученных постов, удаление исчезнувших, возврат списка новых.
     *
     * @param  PostDTO[]  $posts
     * @return array<int, array{external_id: string, title: string}>
     */
    private function syncPosts(Blog $blog, array $posts): array
    {
        /** @var string[] $existingExternalIds */
        $existingExternalIds = $blog->posts()->pluck('external_id')->all();
        $fetchedExternalIds = array_map(fn (PostDTO $post) => $post->externalId, $posts);

        if ($posts !== []) {
            $now = Carbon::now()->toDateTimeString();

            DB::table('posts')->upsert(
                array_map(fn (PostDTO $post): array => [
                    'blog_id' => $blog->id,
                    'external_id' => $post->externalId,
                    'title' => $post->title,
                    'body' => $post->body,
                    'rating' => $post->rating,
                    'reactions' => json_encode($post->reactions, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $posts),
                ['blog_id', 'external_id'],
                ['title', 'body', 'rating', 'reactions', 'updated_at'],
            );
        }

        $toDelete = array_diff($existingExternalIds, $fetchedExternalIds);

        if ($toDelete !== []) {
            $blog->posts()->whereIn('external_id', array_values($toDelete))->delete();
        }

        $newExternalIds = array_flip(array_diff($fetchedExternalIds, $existingExternalIds));

        return array_values(array_map(
            fn (PostDTO $post): array => ['external_id' => $post->externalId, 'title' => $post->title],
            array_filter($posts, fn (PostDTO $post): bool => isset($newExternalIds[$post->externalId])),
        ));
    }
}
