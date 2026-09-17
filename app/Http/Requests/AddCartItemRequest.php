<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'size_id' => ['nullable', 'integer', 'exists:product_sizes,id'],
            'modifier_ids' => ['nullable', 'array'],
            'modifier_ids.*' => ['integer', 'exists:modifiers,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
