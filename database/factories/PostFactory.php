<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'blog_id' => Blog::factory(),
            'external_id' => fake()->unique()->uuid(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(2, true),
            'rating' => fake()->randomFloat(1, 1.0, 5.0),
            'reactions' => [
                'likes' => fake()->numberBetween(0, 200),
                'loves' => fake()->numberBetween(0, 100),
                'wows' => fake()->numberBetween(0, 50),
                'hahas' => fake()->numberBetween(0, 30),
            ],
        ];
    }
}
