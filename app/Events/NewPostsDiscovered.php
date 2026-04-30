<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Blog;
use Illuminate\Foundation\Events\Dispatchable;

final class NewPostsDiscovered
{
    use Dispatchable;

    /**
     * @param  array<int, array{external_id: string, title: string}>  $newPosts
     */
    public function __construct(
        public readonly Blog $blog,
        public readonly array $newPosts,
    ) {}
}
