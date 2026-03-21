<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskDispatchRequest;
use App\Jobs\LogTaskRequestJob;
use App\Models\RunLog;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * Validate incoming payload, persist a task, and enqueue follow-up work.
     */
    public function store(StoreTaskDispatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $task = Task::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'status' => 'queued',
            'input_json' => $validated['input'],
            'meta_json' => $validated['meta'] ?? null,
        ]);

        RunLog::query()->create([
            'task_id' => $task->id,
            'level' => 'info',
            'event_type' => 'task.accepted',
            'message' => 'Task request accepted and persisted',
            'context_json' => [
                'task_public_id' => $task->public_id,
                'user_id' => $request->user()?->id,
                'input' => $validated,
            ],
        ]);

        Log::info('Task dispatch request accepted', [
            'task_public_id' => $task->public_id,
            'user_id' => $request->user()?->id,
            'input' => $validated,
        ]);

        LogTaskRequestJob::dispatch($task->id)->onQueue('ai');

        return response()->json([
            'status' => $task->status,
            'task_public_id' => $task->public_id,
            'dispatch_id' => $task->public_id,
        ], 202);
    }

    /**
     * Return the current state of a task owned by the authenticated user.
     */
    public function show(Request $request, string $taskPublicId): JsonResponse
    {
        $task = $this->findUserTaskOrFail($request, $taskPublicId);

        return response()->json([
            'data' => [
                'public_id' => $task->public_id,
                'type' => $task->type,
                'status' => $task->status,
                'priority' => $task->priority,
                'input' => $task->input_json,
                'meta' => $task->meta_json,
                'error_message' => $task->error_message,
                'created_at' => optional($task->created_at)?->toIso8601String(),
                'updated_at' => optional($task->updated_at)?->toIso8601String(),
                'started_at' => optional($task->started_at)?->toIso8601String(),
                'finished_at' => optional($task->finished_at)?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Return task logs for a task owned by the authenticated user.
     */
    public function logs(Request $request, string $taskPublicId): JsonResponse
    {
        $task = $this->findUserTaskOrFail($request, $taskPublicId);
        $logs = $task->logs()->orderBy('created_at')->get();

        return response()->json([
            'data' => $logs->map(fn (RunLog $log): array => [
                'id' => $log->id,
                'level' => $log->level,
                'event_type' => $log->event_type,
                'message' => $log->message,
                'context' => $log->context_json,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])->all(),
        ]);
    }

    protected function findUserTaskOrFail(Request $request, string $taskPublicId): Task
    {
        return Task::query()
            ->where('public_id', $taskPublicId)
            ->where('user_id', $request->user()?->id)
            ->firstOrFail();
    }
}
