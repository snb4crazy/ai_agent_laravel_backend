<?php

namespace App\Traits;

use App\Models\RunLog;
use App\Models\Task;

/**
 * Provides a unified log() helper for Job classes that write activity
 * entries to the run_logs table, always including the task's public_id
 * in the context payload.
 */
trait LogsTaskActivity
{
    /**
     * Persist a structured log entry for the given task.
     *
     * @param  array<string, mixed>  $extra
     */
    private function log(Task $task, string $level, string $event, string $message, array $extra = []): void
    {
        RunLog::query()->create([
            'task_id' => $task->id,
            'level' => $level,
            'event_type' => $event,
            'message' => $message,
            'context_json' => array_merge(['task_public_id' => $task->public_id], $extra),
        ]);
    }
}
