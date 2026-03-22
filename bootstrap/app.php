<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Domain exception — already has code, status and message
            if ($e instanceof ApiException) {
                report($e);

                return $e->render();
            }

            // Validation errors
            if ($e instanceof ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'errors' => $e->errors(),
                    ],
                ], 422);
            }

            // Model not found / route model binding
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }

            // Unauthenticated — distinguish expired token from missing/invalid token
            if ($e instanceof AuthenticationException) {
                $bearerToken = $request->bearerToken();

                if ($bearerToken) {
                    $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);

                    if ($tokenRecord && $tokenRecord->expires_at?->isPast()) {
                        return response()->json([
                            'error' => [
                                'code'    => 'TOKEN_EXPIRED',
                                'message' => 'Your session has expired. Please log in again.',
                                'action'  => 'relogin',
                            ],
                        ], 401);
                    }
                }

                return response()->json([
                    'error' => [
                        'code'    => 'UNAUTHENTICATED',
                        'message' => 'Unauthenticated.',
                    ],
                ], 401);
            }

            // Generic HTTP exceptions (403, 405, 429, etc.)
            if ($e instanceof HttpException) {
                return response()->json([
                    'error' => [
                        'code' => 'HTTP_ERROR',
                        'message' => $e->getMessage() ?: 'HTTP error.',
                    ],
                ], $e->getStatusCode());
            }

            // Unexpected errors — log and return a safe message
            report($e);

            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred.',
                    'detail' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        });
    })->create();
