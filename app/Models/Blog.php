<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Блог про котов, отслеживаемый системой мониторинга.
 *
 * @property int $id
 * @property int $resource_id
 * @property string $external_id
 * @property string $name
 * @property float $rating
 * @property string|null $cat_name
 * @property string|null $author
 * @property int $monitor_frequency_hours
 * @property Carbon|null $next_check_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Blog extends Model
{
    /** @use HasFactory<BlogFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_id',
        'external_id',
        'name',
        'rating',
        'cat_name',
        'author',
        'monitor_frequency_hours',
        'next_check_at',
    ];

    protected $casts = [
        'rating' => 'float',
        'monitor_frequency_hours' => 'integer',
        'next_check_at' => 'datetime',
    ];

    /**
     * Источник данных, к которому принадлежит блог.
     *
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Посты блога.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Записи журнала мониторинга для данного блога.
     *
     * @return HasMany<MonitoringLog, $this>
     */
    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }
}
