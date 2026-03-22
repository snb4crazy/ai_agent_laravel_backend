<?php

namespace App\Exceptions;

class TaskException extends ApiException
{
    public static function dispatchFailed(\Throwable $previous): self
    {
        return new self(
            'Task could not be queued. Please try again.',
            'TASK_DISPATCH_FAILED',
            500,
            $previous,
        );
    }

    public static function notFound(): self
    {
        return new self(
            'Task not found.',
            'TASK_NOT_FOUND',
            404,
        );
    }
}
