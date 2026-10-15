<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.max' => 'Имя не должно быть длиннее 50 символов.',
            'notifications_enabled.boolean' => 'Уведомления должны быть true или false.',
            'fcm_token.max' => 'Токен уведомлений слишком длинный.',
        ];
    }
}
