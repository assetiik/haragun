<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cryptogram' => ['required', 'string', 'min:20'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
