<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BaseAppException extends Exception
{
    public function __construct(
        string $message = 'An unexpected error occurred.',
        private readonly string $errorCode = 'INTERNAL_ERROR',
        private readonly int $httpStatus = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getMessage(),
            ],
        ], $this->getHttpStatus());
    }
}
