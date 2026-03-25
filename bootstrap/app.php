<?php

use App\Exceptions\ApiException;
use App\Exceptions\BaseAppException;
use App\Exceptions\DomainAppException;
use App\Exceptions\ExternalAppException;
use App\Exceptions\InfrastructureAppException;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $buildExceptionContext = static function (?Request $request = null): array {
            $request ??= request();

            if (! $request instanceof Request) {
                return [];
            }

            $route = $request->route();

            return [
                'request_id' => $request->header('X-Request-Id')
                    ?? $request->header('request_id')
                    ?? $request->attributes->get('request_id'),
                'route' => $route?->getName(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'task_public_id' => $request->header('X-Task-Public-Id')
                    ?? $request->header('task_public_id')
                    ?? $request->route('taskPublicId')
                    ?? $request->route('task_public_id'),
            ];
        };

        $exceptions->report(function (Throwable $e) use ($buildExceptionContext): void {
            $family = match (true) {
                $e instanceof DomainAppException => 'domain',
                $e instanceof InfrastructureAppException => 'infrastructure',
                $e instanceof ExternalAppException => 'external',
                default => 'unmapped',
            };

            Log::error('Application exception captured', [
                'family' => $family,
                'type' => $e::class,
                'message' => $e->getMessage(),
                ...$buildExceptionContext(),
                // TODO: include run_public_id once agent runs are fully wired into API routes/jobs.
            ]);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            /*
             |------------------------------------------------------------------
             | Exception Family Mapping (API)
             |------------------------------------------------------------------
             | Domain: App\Exceptions\DomainAppException (and ApiException)
             | Infrastructure: App\Exceptions\InfrastructureAppException
             | External: App\Exceptions\ExternalAppException
             |
             | TODO: expand mapping with provider-specific external errors
             | (Azure/OpenAI throttling, auth, malformed responses) and add
             | retry hints for frontend/worker handling.
             */

            // Domain exception — already has code, status and message
            if ($e instanceof ApiException || $e instanceof BaseAppException) {
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
                    $tokenRecord = PersonalAccessToken::findToken($bearerToken);

                    if ($tokenRecord && $tokenRecord->expires_at?->isPast()) {
                        return response()->json([
                            'error' => [
                                'code' => 'TOKEN_EXPIRED',
                                'message' => 'Your session has expired. Please log in again.',
                                'action' => 'relogin',
                            ],
                        ], 401);
                    }
                }

                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
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

            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred.',
                    'detail' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        });
    })->create();
