<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blog>
 */
final class BlogFactory extends Factory
{
    protected $model = Blog::class;

    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory(),
            'external_id' => fake()->unique()->uuid(),
            'name' => fake()->words(3, true),
            'rating' => fake()->randomFloat(1, 1.0, 5.0),
            'cat_name' => fake()->firstName(),
            'author' => fake()->name(),
            'monitor_frequency_hours' => fake()->numberBetween(4, 8),
            'next_check_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ];
    }

    /**
     * Блог, у которого подошло время проверки.
     */
    public function due(): static
    {
        return $this->state(['next_check_at' => now()->subHour()]);
    }

    /**
     * Блог, у которого ещё не подошло время проверки.
     */
    public function notDue(): static
    {
        return $this->state(['next_check_at' => now()->addHours(3)]);
    }
}
