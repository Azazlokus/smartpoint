<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\NewPostsDiscovered;
use Illuminate\Support\Facades\Log;

final class LogNewPostsDiscovered
{
    public function handle(NewPostsDiscovered $event): void
    {
        Log::info('monitoring.new_posts_discovered', [
            'blog_id' => $event->blog->id,
            'external_id' => $event->blog->external_id,
            'source' => $event->blog->resource->slug,
            'count' => count($event->newPosts),
            'posts' => $event->newPosts,
        ]);
    }
}
