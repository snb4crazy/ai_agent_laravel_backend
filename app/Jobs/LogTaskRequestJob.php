<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\RunLog;
use App\Models\Task;
use App\Services\TaskActionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LogTaskRequestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $taskId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TaskActionService $taskActionService): void
    {
        $task = null;

        try {
            $task = Task::query()->findOrFail($this->taskId);

            $task->fill([
                'status' => TaskStatus::PROCESSING,
                'started_at' => $task->started_at ?? now(),
                'error_message' => null,
            ])->save();

            RunLog::query()->create([
                'task_id' => $task->id,
                'level' => 'info',
                'event_type' => 'task.job_received',
                'message' => 'Queue job received persisted task payload',
                'context_json' => [
                    'task_public_id' => $task->public_id,
                    'user_id' => $task->user_id,
                    'input' => [
                        'type' => $task->type,
                        'input' => $task->input_json,
                        'meta' => $task->meta_json,
                    ],
                ],
            ]);

            $actionExecution = $taskActionService->execute($task->type, (array) $task->input_json);

            if ($actionExecution['executed']) {
                $meta = (array) ($task->meta_json ?? []);
                $meta['action_result'] = $actionExecution['result'];
                $meta['action_name'] = $actionExecution['action'];

                $task->fill([
                    'meta_json' => $meta,
                ])->save();

                RunLog::query()->create([
                    'task_id' => $task->id,
                    'level' => 'info',
                    'event_type' => 'task.action_executed',
                    'message' => 'Action stub executed for task payload',
                    'context_json' => [
                        'task_public_id' => $task->public_id,
                        'action' => $actionExecution['action'],
                        'result' => $actionExecution['result'],
                    ],
                ]);
            }

            Log::info('Task dispatch job received payload', [
                'task_public_id' => $task->public_id,
                'task_id' => $task->id,
                'user_id' => $task->user_id,
                'input' => [
                    'type' => $task->type,
                    'input' => $task->input_json,
                    'meta' => $task->meta_json,
                ],
            ]);

            $task->fill([
                'status' => TaskStatus::COMPLETED,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            if ($task !== null) {
                try {
                    $task->fill([
                        'status' => TaskStatus::FAILED,
                        'error_message' => $exception->getMessage(),
                        'finished_at' => now(),
                    ])->save();
                } catch (\Throwable) {
                    // Best-effort status update only; original exception is rethrown below.
                }
            }

            Log::error('Task dispatch job failed', [
                'task_public_id' => $task?->public_id,
                'task_id' => $task?->id ?? $this->taskId,
                'user_id' => $task?->user_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
