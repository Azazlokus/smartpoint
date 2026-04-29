<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<resource>
 */
final class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'url' => fake()->url(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Источник с slug = 'mock' (для MockAdapter).
     */
    public function mock(): static
    {
        return $this->state(['slug' => 'mock']);
    }

    /**
     * Отключённый источник.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
