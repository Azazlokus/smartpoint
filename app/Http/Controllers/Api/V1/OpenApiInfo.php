<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SmartPoint — Cat Blog Monitor API',
    description: 'REST API для управления блогами в системе мониторинга кошачьих блогов.',
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]

// ── Переиспользуемые схемы ────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'ResourceObject',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mock Source'),
        new OA\Property(property: 'slug', type: 'string', example: 'mock'),
    ],
)]
#[OA\Schema(
    schema: 'Blog',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'external_id', type: 'string', example: 'blog_42'),
        new OA\Property(property: 'name', type: 'string', example: 'Мурзик daily'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.8),
        new OA\Property(property: 'cat_name', type: 'string', nullable: true, example: 'Мурзик'),
        new OA\Property(property: 'author', type: 'string', nullable: true, example: 'Иван'),
        new OA\Property(property: 'monitor_frequency_hours', type: 'integer', example: 4),
        new OA\Property(property: 'next_check_at', type: 'string', format: 'date-time', nullable: true, example: '2026-04-26T12:00:00+00:00'),
        new OA\Property(property: 'resource', ref: '#/components/schemas/ResourceObject'),
    ],
)]

#[OA\Schema(
    schema: 'MonitoringLogItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2026-04-30T12:00:00+00:00'),
        new OA\Property(property: 'new_posts_count', type: 'integer', example: 3),
        new OA\Property(
            property: 'new_posts',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'external_id', type: 'string', example: 'post_1'),
                    new OA\Property(property: 'title', type: 'string', example: 'Мурзик гуляет'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Schema(
    schema: 'MonitoringLogCollection',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Логи мониторинга получены.'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(
                    property: 'pagination',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    ],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MonitoringLogItem'),
                ),
            ],
            type: 'object',
        ),
    ],
)]

// Ответ для GET /blogs — обёртка ApiNanoPaginator
#[OA\Schema(
    schema: 'BlogCollection',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Список блогов получен.'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(
                    property: 'pagination',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 100),
                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                    ],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Blog'),
                ),
            ],
            type: 'object',
        ),
    ],
)]

// Ответ для POST /blogs и GET /blogs/{id} — обёртка Api::success/created
#[OA\Schema(
    schema: 'BlogResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Блог найден.'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Blog'),
    ],
)]
#[OA\Schema(
    schema: 'StoreBlogRequest',
    required: ['resource_id', 'external_id', 'monitor_frequency_hours'],
    properties: [
        new OA\Property(property: 'resource_id', type: 'integer', example: 1, description: 'ID источника'),
        new OA\Property(property: 'external_id', type: 'string', example: 'blog_42', description: 'ID блога на стороне источника'),
        new OA\Property(property: 'monitor_frequency_hours', type: 'integer', minimum: 4, maximum: 8, example: 4, description: 'Частота мониторинга (4–8 часов)'),
    ],
)]
#[OA\Schema(
    schema: 'UpdateBlogRequest',
    required: ['monitor_frequency_hours'],
    properties: [
        new OA\Property(property: 'monitor_frequency_hours', type: 'integer', minimum: 4, maximum: 8, example: 6, description: 'Новая частота мониторинга (4–8 часов)'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Ошибка валидации.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ],
)]
final class OpenApiInfo {}
