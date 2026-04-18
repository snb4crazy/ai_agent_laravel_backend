<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/token', [AuthTokenController::class, 'store']);

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/tasks', [TaskController::class, 'store']);
        Route::post('/tasks/run-pipeline', [TaskController::class, 'runPipeline']);
        Route::post('/tasks/run-action', [TaskController::class, 'runAction']);
        Route::post('/tasks/run-policy-pipeline', [TaskController::class, 'runPolicyGuidedPipeline']);
        Route::get('/tasks/{taskPublicId}', [TaskController::class, 'show'])->name('api.v1.tasks.show');
        Route::get('/tasks/{taskPublicId}/logs', [TaskController::class, 'logs'])->name('api.v1.tasks.logs');
    });
});
