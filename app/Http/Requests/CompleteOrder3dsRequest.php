<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOrder3dsRequest extends FormRequest
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
            'transaction_id' => ['required', 'integer', 'min:1'],
            'pa_res' => ['required', 'string', 'min:10'],
        ];
    }
}
