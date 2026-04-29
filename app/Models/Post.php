<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Пост блога, синхронизированный из внешнего источника.
 *
 * @property int $id
 * @property int $blog_id
 * @property string $external_id
 * @property string $title
 * @property string $body
 * @property float $rating
 * @property array<string, int>|null $reactions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_id',
        'external_id',
        'title',
        'body',
        'rating',
        'reactions',
    ];

    protected $casts = [
        'rating' => 'float',
        'reactions' => 'array',
    ];

    /**
     * Блог, которому принадлежит пост.
     *
     * @return BelongsTo<Blog, $this>
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
