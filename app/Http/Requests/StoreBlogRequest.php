<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Api;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreBlogRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'resource_id'             => ['required', 'integer', 'exists:resources,id'],
            'external_id'             => ['required', 'string', 'max:255'],
            'monitor_frequency_hours' => ['required', 'integer', 'between:4,8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resource_id.required'            => 'Укажите источник (resource_id).',
            'resource_id.exists'              => 'Источник с указанным ID не найден.',
            'external_id.required'            => 'Укажите внешний идентификатор блога.',
            'monitor_frequency_hours.between' => 'Частота мониторинга должна быть от 4 до 8 часов.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            Api::unprocessableEntity('Ошибка валидации.', $validator->errors()->toArray())
        );
    }
}
