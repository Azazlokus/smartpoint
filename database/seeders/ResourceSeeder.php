<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

final class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        Resource::firstOrCreate(
            ['slug' => 'mock'],
            [
                'name' => 'Mock Source',
                'url' => 'https://mock.example.com',
                'description' => 'Тестовый источник с фиктивными данными. Используется для локальной разработки.',
                'is_active' => true,
            ],
        );
    }
}
