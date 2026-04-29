<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

final class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $resource = Resource::where('slug', 'mock')->firstOrFail();

        for ($i = 1; $i <= 10; $i++) {
            Blog::firstOrCreate(
                [
                    'resource_id' => $resource->id,
                    'external_id' => 'blog_'.$i,
                ],
                [
                    'name' => 'Cat Blog #'.$i,
                    'rating' => round(mt_rand(10, 50) / 10, 1),
                    'cat_name' => 'Cat_'.$i,
                    'author' => 'Author_'.$i,
                    'monitor_frequency_hours' => mt_rand(4, 8),
                    'next_check_at' => Carbon::now()->subMinutes(mt_rand(0, 120)),
                ],
            );
        }
    }
}
