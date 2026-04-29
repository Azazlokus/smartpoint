<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MonitoringLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Запись журнала мониторинга одного цикла проверки блога.
 *
 * @property int $id
 * @property int $blog_id
 * @property Carbon $date
 * @property array<int, array{external_id: string, title: string}> $new_posts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MonitoringLog extends Model
{
    /** @use HasFactory<MonitoringLogFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_id',
        'date',
        'new_posts',
    ];

    protected $casts = [
        'date' => 'datetime',
        'new_posts' => 'array',
    ];

    /**
     * Блог, к которому относится данная запись лога.
     *
     * @return BelongsTo<Blog, $this>
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
