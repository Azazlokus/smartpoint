<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blog;
use App\Models\MonitoringLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringLog>
 */
final class MonitoringLogFactory extends Factory
{
    protected $model = MonitoringLog::class;

    public function definition(): array
    {
        return [
            'blog_id' => Blog::factory(),
            'date' => now(),
            'new_posts' => [
                ['external_id' => fake()->uuid(), 'title' => fake()->sentence()],
            ],
        ];
    }

    /**
     * Лог без новых постов (все посты уже были известны).
     */
    public function empty(): static
    {
        return $this->state(['new_posts' => []]);
    }
}
