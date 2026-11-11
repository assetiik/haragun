<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RinkApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
        public readonly ?string $path = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_filter([
            'path' => $this->path,
            'status' => $this->status,
            'code' => $this->errorCode,
            'details' => $this->details === [] ? null : $this->details,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
