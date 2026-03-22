<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    private string $errorCode;
    private int $httpStatus;

    public function __construct(
        string $message = 'An unexpected error occurred.',
        string $errorCode = 'INTERNAL_ERROR',
        int $httpStatus = 500,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
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
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ], $this->httpStatus);
    }
}
