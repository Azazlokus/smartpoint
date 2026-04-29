<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * Фабрика стандартизированных JSON-ответов API.
 */
final class Api
{
    /** @param array<string, mixed> $errors */
    public static function clientError(string $message, array $errors = [], int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        Assert::range($status, 400, 499, 'HTTP-статус клиентской ошибки должен быть в диапазоне 4xx');

        return response()->json(['message' => $message ?: 'ошибка на стороне клиента', 'errors' => (object) $errors], $status);
    }

    /** @param array<string, mixed> $errors */
    public static function serverError(string $message, array $errors = [], int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        Assert::range($status, 500, 599, 'HTTP-статус серверной ошибки должен быть в диапазоне 5xx');

        return response()->json(['message' => $message ?: 'ошибка на стороне сервера', 'errors' => (object) $errors], $status);
    }

    /** @param array<string, mixed> $data */
    public static function success(?string $message = null, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(['message' => $message ?: 'успешно', 'data' => (object) $data], $status);
    }

    /** @param array<string, mixed> $data */
    public static function created(?string $message = null, array $data = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'ресурс создан', 'data' => (object) $data], Response::HTTP_CREATED);
    }

    /** @param array<string, mixed> $data */
    public static function unauthenticated(?string $message = null, array $data = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'не аутентифицирован', 'data' => (object) $data], Response::HTTP_UNAUTHORIZED);
    }

    /** @param array<string, mixed> $data */
    public static function unauthorized(?string $message = null, array $data = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'доступ запрещён', 'data' => (object) $data], Response::HTTP_FORBIDDEN);
    }

    /** @param array<string, mixed> $data */
    public static function notFound(?string $message = null, array $data = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'ресурс не найден', 'data' => (object) $data], Response::HTTP_NOT_FOUND);
    }

    /** @param array<string, mixed> $errors */
    public static function unprocessableEntity(?string $message = null, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'ошибка валидации', 'errors' => (object) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @param array<string, mixed> $errors */
    public static function tooManyRequests(?string $message = null, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'слишком много запросов', 'errors' => (object) $errors], Response::HTTP_TOO_MANY_REQUESTS);
    }

    /** @param array<string, mixed> $errors */
    public static function gone(?string $message = null, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'ресурс удалён', 'errors' => (object) $errors], Response::HTTP_GONE);
    }

    /** @param array<string, mixed> $data */
    public static function teapot(?string $message = null, array $data = []): JsonResponse
    {
        return response()->json(['message' => $message ?: 'я чайник', 'data' => (object) $data], Response::HTTP_I_AM_A_TEAPOT);
    }
}
