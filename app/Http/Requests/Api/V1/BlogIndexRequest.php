<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Api;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Запрос для GET /api/v1/blogs.
 *
 * Поддерживаемые параметры:
 *   filter[name]          — поиск по названию блога (LIKE)
 *   filter[resource_id]   — точное совпадение по источнику
 *   filter[rating_from]   — минимальный рейтинг
 *   sort                  — сортировка (rating_asc, rating_desc, name_asc, name_desc, newest)
 *   per_page              — размер страницы (1–100, по умолчанию 20)
 *   page                  — номер страницы
 */
final class BlogIndexRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter.name'        => ['sometimes', 'string', 'max:255'],
            'filter.resource_id' => ['sometimes', 'integer', 'exists:resources,id'],
            'filter.rating_from' => ['sometimes', 'numeric', 'min:0'],
            'sort'               => ['sometimes', 'string', 'in:rating_asc,rating_desc,name_asc,name_desc,newest'],
            'per_page'           => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'               => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'filter.rating_from.min' => 'Минимальный рейтинг не может быть отрицательным.',
            'filter.resource_id.exists' => 'Источник с указанным ID не найден.',
            'sort.in' => 'Допустимые значения сортировки: rating_asc, rating_desc, name_asc, name_desc, newest.',
            'per_page.max' => 'Максимальный размер страницы — 100.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            Api::unprocessableEntity('Ошибка валидации.', $validator->errors()->toArray())
        );
    }
}
