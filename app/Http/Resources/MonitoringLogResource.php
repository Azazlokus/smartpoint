<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MonitoringLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MonitoringLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MonitoringLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'date' => $log->date->toIso8601String(),
            'new_posts_count' => count($log->new_posts),
            'new_posts' => $log->new_posts,
        ];
    }
}
