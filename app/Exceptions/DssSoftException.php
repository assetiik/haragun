<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DssSoftException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
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
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
