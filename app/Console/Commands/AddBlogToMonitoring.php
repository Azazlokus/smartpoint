<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Resource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class AddBlogToMonitoring extends Command
{
    /**
     * Сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'blog:add
                            {resource_slug   : Slug источника (например: mock, wordpress)}
                            {external_id     : ID блога на стороне источника}
                            {--frequency=4   : Частота мониторинга в часах (4–8)}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Добавить блог на мониторинг';

    public function handle(): int
    {
        $slug        = $this->argument('resource_slug');
        $externalId  = $this->argument('external_id');
        $frequency   = (int) $this->option('frequency');

        if ($frequency < 4 || $frequency > 8) {
            $this->error('Частота мониторинга должна быть от 4 до 8 часов.');

            return self::FAILURE;
        }

        $resource = Resource::where('slug', $slug)->where('is_active', true)->first();

        if ($resource === null) {
            $this->error("Активный источник со slug [{$slug}] не найден.");
            $this->line('Доступные источники: ' . Resource::where('is_active', true)->pluck('slug')->join(', '));

            return self::FAILURE;
        }

        $alreadyExists = Blog::where('resource_id', $resource->id)
            ->where('external_id', $externalId)
            ->exists();

        if ($alreadyExists) {
            $this->warn("Блог [{$externalId}] из источника [{$slug}] уже на мониторинге.");

            return self::FAILURE;
        }

        $blog = Blog::create([
            'resource_id'             => $resource->id,
            'external_id'             => $externalId,
            'name'                    => $externalId,
            'rating'                  => 0.0,
            'monitor_frequency_hours' => $frequency,
            'next_check_at'           => Carbon::now(),
        ]);

        $this->info("Блог добавлен на мониторинг.");
        $this->table(
            ['ID', 'Источник', 'External ID', 'Частота'],
            [[$blog->id, $slug, $externalId, "{$frequency}ч"]],
        );

        return self::SUCCESS;
    }
}
