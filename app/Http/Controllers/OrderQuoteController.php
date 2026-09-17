<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteOrderRequest;
use App\Services\OrderQuoteService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Arena', weight: 4)]
class OrderQuoteController extends Controller
{
    public function __construct(private OrderQuoteService $quotes) {}

    public function __invoke(QuoteOrderRequest $request): JsonResponse
    {
        return response()->json(
            $this->quotes->quote($request->validated())
        );
    }
}
