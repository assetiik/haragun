<?php

namespace App\Http\Requests;

use App\Enums\FeedbackRating;
use App\Enums\FeedbackTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderFeedbackRequest extends FormRequest
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
        $likeIds = collect(FeedbackTag::likeOptions())->map->value->all();
        $dislikeIds = collect(FeedbackTag::dislikeOptions())->map->value->all();

        return [
            'rating' => ['required', 'string', Rule::enum(FeedbackRating::class)],
            'likes' => ['nullable', 'array'],
            'likes.*' => ['string', Rule::in($likeIds)],
            'dislikes' => ['nullable', 'array'],
            'dislikes.*' => ['string', Rule::in($dislikeIds)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
