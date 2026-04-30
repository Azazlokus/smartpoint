<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Api;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class UpdateBlogRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'monitor_frequency_hours' => ['required', 'integer', 'between:4,8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'monitor_frequency_hours.required' => 'Укажите частоту мониторинга.',
            'monitor_frequency_hours.between' => 'Частота мониторинга должна быть от 4 до 8 часов.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            Api::unprocessableEntity('Ошибка валидации.', $validator->errors()->toArray()),
        );
    }
}
