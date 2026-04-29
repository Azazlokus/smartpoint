<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Blog;

interface MonitoringServiceInterface
{
    /**
     * Выполняет один цикл мониторинга для указанного блога.
     */
    public function monitor(Blog $blog): void;
}
