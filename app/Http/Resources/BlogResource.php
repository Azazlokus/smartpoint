<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BlogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Blog $blog */
        $blog = $this->resource;

        return [
            'id' => $blog->id,
            'external_id' => $blog->external_id,
            'name' => $blog->name,
            'rating' => $blog->rating,
            'cat_name' => $blog->cat_name,
            'author' => $blog->author,
            'monitor_frequency_hours' => $blog->monitor_frequency_hours,
            'next_check_at' => $blog->next_check_at?->toIso8601String(),
            'resource' => [
                'id' => $blog->resource->id,
                'name' => $blog->resource->name,
                'slug' => $blog->resource->slug,
            ],
        ];
    }
}
