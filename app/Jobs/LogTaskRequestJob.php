<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\RunLog;
use App\Models\Task;
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
    public function handle(): void
    {
        $task = Task::query()->findOrFail($this->taskId);

        $task->forceFill([
            'status' => TaskStatus::PROCESSING,
            'started_at' => $task->started_at ?? now(),
            'error_message' => null,
        ])->save();

        try {
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

            $task->forceFill([
                'status' => TaskStatus::COMPLETED,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $task->forceFill([
                'status' => TaskStatus::FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            Log::error('Task dispatch job failed', [
                'task_public_id' => $task->public_id,
                'task_id' => $task->id,
                'user_id' => $task->user_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
