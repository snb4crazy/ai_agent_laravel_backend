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
        Route::get('/tasks/{taskPublicId}', [TaskController::class, 'show']);
        Route::get('/tasks/{taskPublicId}/logs', [TaskController::class, 'logs']);
    });
});

